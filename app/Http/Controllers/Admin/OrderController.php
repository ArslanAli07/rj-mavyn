<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrderController extends Controller
{
    use Concerns\HandlesBulkActions;
    public function poll(Request $request): JsonResponse
    {
        // Close the session immediately so this 2s poller does not hold the
        // database session lock and block other admin page requests.
        if ($request->hasSession()) {
            $request->session()->save();
        }

        $snapshot = Cache::remember('admin_orders_poll_snapshot', 2, function () {
            return [
                'latest_id'     => (int) (Order::max('id') ?? 0),
                'pending_count' => Order::where('status', 'pending')->count(),
            ];
        });

        $latestId = $snapshot['latest_id'];
        $afterId  = (int) $request->query('after_id', 0);

        // Keep payload tiny: only the newest order for the toast, never a full backlog.
        $order = null;
        $newCount = 0;
        if ($afterId > 0 && $latestId > $afterId) {
            $newCount = $latestId - $afterId;
            $latest = Order::query()
                ->where('id', '>', $afterId)
                ->orderByDesc('id')
                ->first(['id', 'order_number', 'customer_name', 'grand_total']);

            if ($latest) {
                $order = [
                    'id'           => $latest->id,
                    'order_number' => $latest->order_number,
                    'customer_name' => $latest->customer_name,
                    'grand_total'  => $latest->grand_total,
                    'url'          => route('admin.orders.show', $latest),
                ];
            }
        }

        return response()
            ->json([
                'latest_id'     => $latestId,
                'new_count'     => $newCount,
                'order'         => $order,
                'pending_count' => $snapshot['pending_count'],
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function index(Request $request): View
    {
        $query = Order::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($q2) use ($q) {
                $q2->where('order_number', 'LIKE', "%{$q}%")
                   ->orWhere('customer_name', 'LIKE', "%{$q}%")
                   ->orWhere('customer_email', 'LIKE', "%{$q}%");
            });
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $orders   = $query->paginate(20)->withQueryString();
        $statuses = Order::STATUSES;
        $methods  = Order::PAYMENT_METHODS;

        return view('admin.orders.index', compact('orders', 'statuses', 'methods'));
    }

    public function show(Order $order): View
    {
        $order->load('items', 'user', 'coupon');
        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'status'          => 'required|in:' . implode(',', array_keys(Order::STATUSES)),
            'payment_status'  => 'required|in:pending,paid,failed,refunded',
            'admin_notes'     => 'nullable|string|max:1000',
        ]);

        $data = [
            'status'         => $request->status,
            'payment_status' => $request->payment_status,
        ];

        if ($request->filled('admin_notes')) {
            $data['admin_notes'] = $request->admin_notes;
        }

        if ($request->status === 'delivered') {
            $data['delivered_at'] = now();
            $data['payment_status'] = 'paid';
        }

        $oldStatus = $order->status;
        $order->update($data);
        Cache::forget('admin_dashboard_stats');
        Cache::forget('admin_orders_poll_snapshot');

        // Send status update email if status changed, admin checked the box, and globally enabled
        if ($oldStatus !== $request->status && $request->boolean('send_email') && setting('email_order_update', true)) {
            try {
                $order->load('items');
                \Illuminate\Support\Facades\Mail::to($order->customer_email)->send(new \App\Mail\OrderStatusUpdateMail($order));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send order status update email for Order ' . $order->order_number . ': ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Order status and payment updated successfully.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'orders', ['set_status', 'set_payment', 'delete'], [
            'status'          => 'required_if:bulk_action,set_status|nullable|in:' . implode(',', array_keys(Order::STATUSES)),
            'payment_status'  => 'required_if:bulk_action,set_payment|nullable|in:pending,paid,failed,refunded',
        ]);

        $orders = Order::whereIn('id', $data['ids'])->get();

        if ($data['bulk_action'] === 'set_status') {
            $payload = ['status' => $data['status']];
            if ($data['status'] === 'delivered') {
                $payload['delivered_at'] = now();
                $payload['payment_status'] = 'paid';
            }
            Order::whereIn('id', $orders->pluck('id'))->update($payload);
            $message = $orders->count() . ' order' . ($orders->count() === 1 ? '' : 's') . ' marked as ' . (Order::STATUSES[$data['status']] ?? $data['status']) . '.';
        } elseif ($data['bulk_action'] === 'set_payment') {
            Order::whereIn('id', $orders->pluck('id'))->update(['payment_status' => $data['payment_status']]);
            $message = $orders->count() . ' order' . ($orders->count() === 1 ? '' : 's') . ' payment set to ' . $data['payment_status'] . '.';
        } else {
            foreach ($orders as $order) {
                if ($order->payment_receipt) {
                    Storage::disk($order->payment_receipt_disk)->delete($order->payment_receipt);
                }
                $order->delete();
            }
            $message = $this->bulkMessage('delete', $orders->count(), 'order');
        }

        Cache::forget('admin_dashboard_stats');
        Cache::forget('admin_orders_poll_snapshot');

        return back()->with('success', $message);
    }

    public function receipt(Order $order): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($order->payment_receipt, 404);
        abort_unless(in_array($order->payment_receipt_disk, ['local', 'public'], true), 404);
        $disk = Storage::disk($order->payment_receipt_disk);
        abort_unless($disk->exists($order->payment_receipt), 404);

        return response()->file($disk->path($order->payment_receipt), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function invoice(Order $order): Response
    {
        $order->load('items', 'user');
    
        // Base height start at 220mm for 1-column layout + full customer address
        $baseHeightMm = 80;
    
        $extraLines = 0;
        foreach ($order->items as $item) {
            $extraLines += 3; // Account for item name wrapping on 25% column
            if ($item->variant_label) $extraLines++;
            $extraLines += count($item->options_snapshot ?? []);
            if ($item->special_request) $extraLines++;
        }
    
        if ((float) $order->discount_amount > 0) $extraLines++;
        if ($order->order_notes) $extraLines += 3;
    
        // Scale dynamically without a restrictive upper cap
        $heightMm = $baseHeightMm + ($extraLines * 6);
    
        $widthMm = 48;
        $widthPt = $widthMm * 72 / 25.4;
        $heightPt = $heightMm * 72 / 25.4;
    
        $pdf = Pdf::loadView('pdf.invoice', compact('order'))
            ->setPaper([0, 0, $widthPt, $heightPt], 'portrait');
    
        return $pdf->stream("invoice-{$order->order_number}.pdf");
    }
}

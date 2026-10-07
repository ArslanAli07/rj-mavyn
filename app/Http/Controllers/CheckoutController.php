<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmationMail;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderAccessService;
use App\Services\OrderPlacementService;
use App\Services\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private OrderPlacementService $orders,
        private PaymentMethodService $payments,
        private OrderAccessService $access,
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $totals = $this->cart->totals();
        $items = $this->cart->items();
        // Eager-load addresses so default_address accessor costs 0 extra queries.
        $addresses = auth()->check() ? auth()->user()->load('addresses')->addresses : collect();
        $defaultAddr = auth()->check() ? auth()->user()->default_address : null;
        $zones = DeliveryZone::active()->orderBy('display_order')->get();

        $enabledPaymentMethods = collect($this->payments->enabled());

        return view('pages.checkout', compact('totals', 'items', 'addresses', 'defaultAddr', 'zones', 'enabledPaymentMethods'));
    }

    public function placeOrder(Request $request): RedirectResponse|JsonResponse
    {
        $enabledMethods = $this->payments->enabled();

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'address_line' => 'required|string|max:500',
            'map_link' => 'nullable|string|max:500',
            'payment_method' => ['required', Rule::in($enabledMethods)],
            'payment_receipt' => [
                $request->payment_method !== 'cod' ? 'required' : 'nullable',
                'image',
                'max:4096',
            ],
            'order_notes' => 'nullable|string|max:1000',
        ]);

        if ($this->cart->count() === 0) {
            $message = 'Your cart is empty.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->route('cart.index')->with('error', $message);
        }

        $order = $this->orders->place($data, $request->file('payment_receipt'));

        // Send order confirmation email (if enabled)
        if (setting('email_order_confirmation', true)) {
            try {
                $order->loadMissing('items');
                Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));
            } catch (\Exception $e) {
                Log::error('Failed to send order confirmation email for Order '.$order->order_number.': '.$e->getMessage());
            }
        }

        // Save order ID in session so guest checkout works on successive pages
        session(['placed_order_id' => $order->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order_id' => $order->id,
                'tracking_url' => route('orders.track-guest', $order->tracking_token),
            ]);
        }

        // COD → success page; manual payments → payment instructions
        if ($request->payment_method === 'cod') {
            return redirect()->route('checkout.success', $order->id)
                ->with('success', 'Order placed successfully! We will confirm your order shortly.');
        }

        return redirect()->route('checkout.payment-instructions', $order->id);
    }

    public function paymentInstructions(Order $order): View|RedirectResponse
    {
        $this->access->authorizeCustomer($order);

        return view('pages.payment-instructions', compact('order'));
    }

    public function success(Order $order): View|RedirectResponse
    {
        $this->access->authorizeCustomer($order);

        return view('pages.order-success', compact('order'));
    }
}

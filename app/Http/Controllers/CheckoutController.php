<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use App\Services\LocalDeliveryService;
use App\Services\OrderAccessService;
use App\Services\OrderPlacementService;
use App\Services\PaymentMethodService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mavyn\Contracts\Plugins\PaymentInstructions;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private OrderPlacementService $orders,
        private PaymentMethodService $payments,
        private OrderAccessService $access,
        private CheckoutAttemptService $attempts,
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->cart->count() === 0) {
            $completed = $this->attempts->owned(session('checkout_key'));
            $order = $completed?->committed_at ? $completed->order : null;
            if ($order) {
                session(['placed_order_id' => $order->id]);

                return redirect()->route($order->payment_method === 'cod' ? 'checkout.success' : 'checkout.payment-instructions', $order);
            }

            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $checkoutKey = $this->attempts->issue();
        $totals = $this->cart->totals();
        $items = $this->cart->items();
        // Eager-load addresses so default_address accessor costs 0 extra queries.
        $addresses = auth()->check() ? auth()->user()->load('addresses')->addresses : collect();
        $defaultAddr = auth()->check() ? auth()->user()->default_address : null;
        $zones = collect(app(LocalDeliveryService::class)->zones())->map(fn ($zone) => (object) ['id' => $zone->id, 'name' => $zone->name, 'delivery_fee' => Money::decimal($zone->feeMinor)]);
        $paymentChoices = $this->payments->choices();

        $enabledPaymentMethods = collect($this->payments->enabled());

        return view('pages.checkout', compact('totals', 'items', 'addresses', 'defaultAddr', 'zones', 'enabledPaymentMethods', 'checkoutKey', 'paymentChoices'));
    }

    public function placeOrder(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => 'required|string|uuid',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'delivery_zone_id' => 'required|integer|min:1',
            'address_line' => 'required|string|max:500',
            'map_link' => 'nullable|string|max:500',
            'payment_method' => 'required|string|max:100',
            'payment_receipt' => [
                'nullable',
                'image',
                'max:4096',
            ],
            'order_notes' => 'nullable|string|max:1000',
        ]);

        $placement = $this->orders->place($data, $request->file('payment_receipt'));
        $order = $placement->order;

        // Save order ID in session so guest checkout works on successive pages
        session(['placed_order_id' => $order->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully!',
                'order_id' => $order->id,
                'replayed' => $placement->replayed,
                'tracking_url' => route('orders.track-guest', $order->tracking_token),
            ]);
        }

        // COD → success page; manual payments → payment instructions
        if ($order->payment_method === 'cod') {
            return redirect()->route('checkout.success', $order->id)
                ->with('success', 'Order placed successfully! We will confirm your order shortly.');
        }

        return redirect()->route('checkout.payment-instructions', $order->id);
    }

    public function paymentInstructions(Order $order): View|RedirectResponse
    {
        $this->access->authorizeCustomer($order);

        $snapshot = $order->payment_instructions_snapshot;
        $paymentInstructions = $snapshot ? new PaymentInstructions($snapshot['label'], $snapshot['instructions'], $snapshot['requires_receipt']) : $this->payments->historical($order->payment_method);

        return view('pages.payment-instructions', compact('order', 'paymentInstructions'));
    }

    public function success(Order $order): View|RedirectResponse
    {
        $this->access->authorizeCustomer($order);

        return view('pages.order-success', compact('order'));
    }
}

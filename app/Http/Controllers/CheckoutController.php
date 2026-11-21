<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutAttemptService;
use App\Services\OrderAccessService;
use App\Services\OrderPlacementService;
use App\Services\PaymentMethodService;
use App\Services\Payments\PaymentService;
use App\Services\ShippingService;
use App\Support\Countries;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mavyn\Contracts\Plugins\PaymentInstructions;
use Mavyn\Contracts\Pricing\CheckoutReview;

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

                return redirect()->route(app(PaymentService::class)->forOrder($order->id) ? 'payments.show' : ($order->payment_method === 'cod' ? 'checkout.success' : 'checkout.payment-instructions'), $order);
            }

            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $checkoutKey = $this->attempts->issue();
        $totals = $this->cart->totals();
        $items = $this->cart->items();
        // Eager-load addresses so default_address accessor costs 0 extra queries.
        $addresses = auth('web')->check() ? auth('web')->user()->load('addresses')->addresses : collect();
        $defaultAddr = auth('web')->check() ? auth('web')->user()->default_address : null;
        $shipping = app(ShippingService::class);
        $shippingChoices = $shipping->choices($shipping->request(Money::minor($totals['subtotal']), (string) setting('store_country', config('site.country'))));
        $shippingOptions = collect($shippingChoices)->map(fn ($choice, $token) => (object) ['id' => $token, 'name' => $choice['option']->label, 'delivery_fee' => Money::decimal($choice['option']->feeMinor), 'legacy_zone_id' => $choice['legacy_zone_id']]);
        // Preserve numeric delivery-zone view data for previously released themes.
        $zones = $shippingOptions->filter(fn ($choice) => $choice->legacy_zone_id !== null)->map(fn ($choice) => (object) ['id' => $choice->legacy_zone_id, 'name' => $choice->name, 'delivery_fee' => $choice->delivery_fee])->values();
        $defaultShippingOption = $shippingOptions->first(fn ($choice) => $defaultAddr && $choice->legacy_zone_id === $defaultAddr->delivery_zone_id)?->id ?? '';
        $paymentChoices = $this->payments->choices();

        $enabledPaymentMethods = collect($this->payments->enabled());

        return view('pages.checkout', compact('totals', 'items', 'addresses', 'defaultAddr', 'zones', 'shippingOptions', 'enabledPaymentMethods', 'checkoutKey', 'paymentChoices', 'defaultShippingOption'));
    }

    public function quote(Request $request): JsonResponse
    {
        $this->defaultBuiltInDispatch($request);
        $input = $request->validate([
            'idempotency_key' => 'required|string|uuid',
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255', 'phone' => 'required|string|max:20',
            'country' => 'required|string|max:100', 'city' => 'required|string|max:100',
            'address_line' => 'required|string|max:500', 'map_link' => 'nullable|string|max:500',
            'shipping_option' => 'nullable|string|max:255', 'delivery_zone_id' => 'nullable|integer|min:1',
            'postal_code' => 'nullable|string|max:30', 'payment_method' => 'required|string|max:100', 'order_notes' => 'nullable|string|max:1000',
        ]);

        return response()->json(app(CheckoutReview::class)->issue($this->normalizeCountry($input)));
    }

    public function options(Request $request): JsonResponse
    {
        $input = $this->normalizeCountry($request->validate(['country' => 'required|string|max:100', 'postal_code' => 'nullable|string|max:30']));
        $shipping = app(ShippingService::class);
        $totals = $this->cart->totals();
        $choices = $shipping->choices($shipping->request(Money::minor($totals['subtotal']), $input['country'], postalCode: $input['postal_code'] ?? null));

        return response()->json(['currency' => $totals['currency'],
            'shipping' => collect($choices)->map(fn ($choice, $token) => ['id' => $token, 'label' => $choice['option']->label, 'fee_minor' => $choice['option']->feeMinor])->values(),
            'payments' => collect($this->payments->choices())->map(fn ($choice, $method) => ['id' => $method, 'label' => $choice['instructions']->label,
                'instructions' => $choice['instructions']->instructions, 'requires_receipt' => $choice['instructions']->requiresReceipt])->values()]);
    }

    public function placeOrder(Request $request): RedirectResponse|JsonResponse
    {
        $this->defaultBuiltInDispatch($request);
        $data = $request->validate([
            'review_token' => 'nullable|string|max:128',
            'idempotency_key' => 'required|string|uuid',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'delivery_zone_id' => 'nullable|required_without:shipping_option|integer|min:1',
            'shipping_option' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:30',
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

        $placement = $this->orders->place($this->normalizeCountry($data), $request->file('payment_receipt'));
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
                'payment_action' => app(PaymentService::class)->checkoutAction($order),
                // Where a non-gateway order continues: COD confirmation, or manual payment instructions.
                'next_url' => $order->payment_method === 'cod' ? route('checkout.success', $order->id) : route('checkout.payment-instructions', $order->id),
            ]);
        }

        if (app(PaymentService::class)->forOrder($order->id)) {
            return redirect()->route('payments.show', $order);
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

    /** Without fulfillment plugins there is nothing to choose, so the single built-in option applies. */
    private function defaultBuiltInDispatch(Request $request): void
    {
        if (! $request->filled('shipping_option') && ! $request->filled('delivery_zone_id') && app(ShippingService::class)->usesBuiltInDispatch()) {
            $request->merge(['shipping_option' => ShippingService::DISPATCH_PROVIDER.':'.ShippingService::DISPATCH_OPTION]);
        }
    }

    /**
     * Store one canonical spelling ("United States") whatever the customer typed ("US", "usa").
     * Quote and placement normalize identically, so the reviewed total and idempotency hash still match.
     */
    private function normalizeCountry(array $data): array
    {
        $code = Countries::code($data['country'] ?? null);
        if ($code !== null) {
            $data['country'] = Countries::name($code);
        }

        return $data;
    }
}

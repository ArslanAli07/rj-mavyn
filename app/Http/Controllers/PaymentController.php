<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderAccessService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function __construct(private OrderAccessService $access, private PaymentService $payments) {}

    public function show(Order $order)
    {
        $this->access->authorizeCustomer($order);
        $payment = $this->payments->forOrder($order->id);
        abort_unless($payment, 404);

        return response()->view('payments.show', compact('order', 'payment'))->header('Cache-Control', 'private, no-store');
    }

    public function start(Request $request, Order $order)
    {
        $this->access->authorizeCustomer($order);
        $action = $this->payments->start($order);
        if ($request->expectsJson()) {
            return response()->json(['payment_action' => (array) $action]);
        }

        return $action->type === 'redirect' ? redirect()->away($action->url, 303) : redirect()->route('payments.show', $order);
    }
}

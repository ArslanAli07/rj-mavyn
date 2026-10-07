<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $orders = auth()->user()->orders()->latest()->paginate(10);
        return view('account.orders', compact('orders'));
    }

    public function show(Order $order): View
    {
        if ($order->user_id !== auth()->id()) abort(403);
        $order->load('items');
        return view('account.order-detail', compact('order'));
    }

    public function trackGuestOrder(string $token): View
    {
        $order = Order::where('tracking_token', $token)->with('items.product')->firstOrFail();
        return view('pages.track-order', compact('order'));
    }
}


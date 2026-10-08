<?php

namespace App\View\Components;

use App\Models\Order;
use Illuminate\View\Component;
use Illuminate\View\View;

class AdminLayout extends Component
{
    public function __construct(public ?string $title = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.admin', [
            'pendingOrdersCount' => auth('admin')->user()?->hasPermission('orders.view') ? Order::where('status', 'pending')->count() : 0,
        ]);
    }
}

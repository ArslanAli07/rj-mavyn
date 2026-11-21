<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mavyn\Contracts\Pricing\PriceBook;

class DashboardController extends Controller
{
    private const LOW_STOCK = 5;

    public function index(Request $request): View
    {
        $reportCurrency = $this->currency($request);
        $stats = (new SalesReport($reportCurrency))->summary() + [
            'currency' => $reportCurrency,
            'total_orders' => Order::count(), 'pending_orders' => Order::where('status', 'pending')->count(),
            'total_products' => Product::active()->count(), 'total_customers' => User::count(),
            'low_stock' => $this->lowStockCount(),
        ];

        $recentOrders = Order::with('user')
            ->latest()
            ->take(8)
            ->get();

        $topProducts = Product::orderByDesc('sales_count')
            ->with('category')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'topProducts', 'reportCurrency'));
    }

    public function chartData(Request $request): JsonResponse
    {
        // Day buckets follow the store timezone so late-evening orders land on the right day.
        $data = (new SalesReport($this->currency($request)))->daily(7);

        return response()->json($data);
    }

    /** Only products whose stock is actually tracked; made-to-order (untracked) items never run low. */
    private function lowStockCount(): int
    {
        $shared = Product::where('is_active', true)->where('inventory_mode', 'shared')
            ->whereRaw('stock_quantity - reserved_quantity <= ?', [self::LOW_STOCK])->count();
        $variant = Product::where('is_active', true)->where('inventory_mode', 'variant')
            ->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('track_inventory', true)
                ->whereRaw('stock_quantity - reserved_quantity <= ?', [self::LOW_STOCK]))->count();

        return $shared + $variant;
    }

    private function currency(Request $request): string
    {
        $currency = $request->input('currency', app(PriceBook::class)->context()->currency);
        $request->merge(['currency' => $currency]);
        $request->validate(['currency' => 'required|in:PKR,USD,EUR,GBP,AUD,CAD,AED,SAR,INR']);

        return $currency;
    }
}

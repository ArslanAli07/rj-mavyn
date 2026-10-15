<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mavyn\Contracts\Pricing\PriceBook;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $reportCurrency = $this->currency($request);
        $orders = Order::sales()->where('currency', $reportCurrency);
        $stats = [
            'currency' => $reportCurrency,
            'total_sales' => (clone $orders)->sum('grand_total'),
            'today_sales' => (clone $orders)->whereDate('created_at', today())->sum('grand_total'),
            'total_discounts' => (clone $orders)->sum('discount_amount'),
            'average_order_value' => (clone $orders)->avg('grand_total') ?? '0.00',
            'total_orders' => Order::count(), 'pending_orders' => Order::where('status', 'pending')->count(),
            'total_products' => Product::active()->count(), 'total_customers' => User::count(),
            'low_stock' => Product::where('stock_quantity', '<=', 5)->where('is_active', true)->count(),
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
        // Single grouped query instead of 14 individual queries (2 per day × 7 days).
        $reportCurrency = $this->currency($request);
        $rows = Order::sales()->where('currency', $reportCurrency)
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(*) as orders')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $row = $rows->get($date);
            $data[] = [
                'currency' => $reportCurrency,
                'date' => now()->subDays($i)->format('M d'),
                'revenue' => (string) ($row?->revenue ?? 0),
                'orders' => (int) ($row?->orders ?? 0),
            ];
        }

        return response()->json($data);
    }

    private function currency(Request $request): string
    {
        $currency = $request->input('currency', app(PriceBook::class)->context()->currency);
        $request->merge(['currency' => $currency]);
        $request->validate(['currency' => 'required|in:PKR,USD,EUR,GBP,AUD,CAD,AED,SAR,INR']);

        return $currency;
    }
}

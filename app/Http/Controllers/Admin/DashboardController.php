<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Wrap all 7 stat queries in a short cache to avoid hitting the DB
        // on every admin dashboard page load.
        $stats = Cache::remember('admin_dashboard_stats', 120, function () {
            return [
                'total_sales'     => (float) Order::sales()->sum('grand_total'),
                'today_sales'     => (float) Order::sales()->whereDate('created_at', today())->sum('grand_total'),
                'total_orders'    => Order::count(),
                'pending_orders'  => Order::where('status', 'pending')->count(),
                'total_products'  => Product::where('is_active', true)->count(),
                'total_customers' => User::count(),
                'low_stock'       => Product::where('stock_quantity', '<=', 5)->where('is_active', true)->count(),
            ];
        });

        $recentOrders = Order::with('user')
            ->latest()
            ->take(8)
            ->get();

        $topProducts = Product::orderByDesc('sales_count')
            ->with('category')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'topProducts'));
    }

    public function chartData(): JsonResponse
    {
        // Single grouped query instead of 14 individual queries (2 per day × 7 days).
        $rows = Order::sales()
            ->selectRaw("DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(*) as orders")
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupByRaw("DATE(created_at)")
            ->get()
            ->keyBy('date');

        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $row  = $rows->get($date);
            $data[] = [
                'date'    => now()->subDays($i)->format('M d'),
                'revenue' => (float) ($row?->revenue ?? 0),
                'orders'  => (int)   ($row?->orders  ?? 0),
            ];
        }

        return response()->json($data);
    }
}

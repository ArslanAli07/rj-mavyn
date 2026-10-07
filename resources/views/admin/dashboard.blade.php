<x-admin-layout title="Admin Dashboard">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        
        {{-- Total Sales --}}
        <div class="bg-white border border-cream-150 rounded-2xl p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center text-xl">💰</div>
            <div>
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider">Total Sales</span>
                <span class="text-xl sm:text-2xl font-bold text-espresso-800 block mt-0.5">{{ format_price($stats['total_sales']) }}</span>
            </div>
        </div>

        {{-- Today Sales --}}
        <div class="bg-white border border-cream-150 rounded-2xl p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center text-xl">📈</div>
            <div>
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider">Today's Sales</span>
                <span class="text-xl sm:text-2xl font-bold text-espresso-800 block mt-0.5">{{ format_price($stats['today_sales']) }}</span>
            </div>
        </div>

        {{-- Pending Orders --}}
        <div class="bg-white border border-cream-150 rounded-2xl p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center text-xl">⏳</div>
            <div>
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider">Pending / Total</span>
                <span class="text-xl sm:text-2xl font-bold text-espresso-800 block mt-0.5">
                    {{ $stats['pending_orders'] }} <span class="text-espresso-400 text-sm">/ {{ $stats['total_orders'] }}</span>
                </span>
            </div>
        </div>

        {{-- Low Stock Alert --}}
        <div class="bg-white border border-cream-150 rounded-2xl p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-red-50 text-red-500 flex items-center justify-center text-xl">⚠️</div>
            <div>
                <span class="text-espresso-400 text-[10px] font-bold uppercase tracking-wider">Low Stock Products</span>
                <span class="text-xl sm:text-2xl font-bold text-espresso-800 block mt-0.5 {{ $stats['low_stock'] > 0 ? 'text-red-650' : '' }}">
                    {{ $stats['low_stock'] }}
                </span>
            </div>
        </div>

    </div>

    {{-- Charts and Top Selling --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        
        {{-- Revenue Chart --}}
        <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm lg:col-span-2 flex flex-col h-96">
            <div class="flex items-center justify-between mb-4 border-b border-cream-100 pb-2">
                <h3 class="font-heading font-bold text-espresso-800 text-sm">7-Day Revenue Trend (PKR)</h3>
            </div>
            <div class="flex-1 min-h-0 relative">
                <canvas id="revenueChart" class="w-full h-full"></canvas>
            </div>
        </div>

        {{-- Top Selling Products --}}
        <div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm flex flex-col h-96">
            <div class="flex items-center justify-between mb-4 border-b border-cream-100 pb-2">
                <h3 class="font-heading font-bold text-espresso-800 text-sm">Best Sellers</h3>
            </div>
            <div class="flex-1 overflow-y-auto space-y-3 pr-1 scrollbar-hide">
                @forelse($topProducts as $p)
                    <div class="flex items-center gap-3">
                        <img src="{{ $p->main_image_url }}" alt="{{ $p->title }}" class="w-10 h-10 rounded-lg object-cover shrink-0">
                        <div class="min-w-0 flex-1 text-xs">
                            <span class="font-bold text-espresso-800 block truncate">{{ $p->title }}</span>
                            <span class="text-espresso-400 block">{{ $p->category?->name }}</span>
                        </div>
                        <span class="text-xs bg-cream-100 text-espresso-650 px-2 py-0.5 rounded-full font-bold shrink-0">x{{ $p->sales_count }}</span>
                    </div>
                @empty
                    <p class="text-xs text-espresso-400 text-center py-12">No sales data recorded.</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Recent Orders --}}
    <div id="admin-orders-live" class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4 border-b border-cream-100 pb-2">
            <h3 class="font-heading font-bold text-espresso-800 text-sm">Recent Orders</h3>
            <a href="{{ route('admin.orders.index') }}" class="text-xs text-primary-600 font-bold hover:underline">View All Orders →</a>
        </div>

        @if($recentOrders->isEmpty())
            <p class="text-xs text-espresso-400 text-center py-8">No orders found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="admin-table text-xs">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentOrders as $order)
                            <tr @class(['order-pending' => $order->status === 'pending'])>
                                <td class="font-bold text-espresso-800">{{ $order->order_number }}</td>
                                <td>
                                    <span class="font-medium text-espresso-800 block">{{ $order->customer_name }}</span>
                                    <span class="text-espresso-400 text-[10px]">{{ $order->customer_phone }}</span>
                                </td>
                                <td class="text-espresso-500">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                                <td class="uppercase text-[10px] font-semibold">{{ $order->payment_method }}</td>
                                <td>
                                    <span class="badge text-[9px] uppercase px-2 py-0.5 rounded-full font-bold
                                                 {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-750' }}">
                                        {{ $order->payment_status }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-{{ $order->status }} text-[9px] uppercase font-bold">
                                        {{ str_replace('_', ' ', $order->status) }}
                                    </span>
                                </td>
                                <td class="font-bold text-primary-650">{{ format_price($order->grand_total) }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="inline-flex items-center justify-center px-3 py-1 bg-cream-100 text-[10px] font-bold text-espresso-800 hover:bg-primary-500 hover:text-white rounded-lg transition-colors">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                fetch('{{ route('admin.chart.data') }}')
                    .then(res => res.json())
                    .then(data => {
                        const labels = data.map(item => item.date);
                        const revenues = data.map(item => item.revenue);

                        const ctx = document.getElementById('revenueChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Daily Revenue (PKR)',
                                    data: revenues,
                                    borderColor: '#C8976A',
                                    backgroundColor: 'rgba(200, 151, 106, 0.1)',
                                    fill: true,
                                    tension: 0.3,
                                    borderWidth: 2
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: '#FEFAF6'
                                        },
                                        ticks: {
                                            color: '#1A0A05',
                                            font: {
                                                size: 10
                                            }
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            color: '#1A0A05',
                                            font: {
                                                size: 10
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    });
            });
        </script>
    @endpush

</x-admin-layout>

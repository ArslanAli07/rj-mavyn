<x-admin-layout title="Admin dashboard">

    <div class="mb-4">
        <h1 class="admin-page-title">Dashboard</h1>
        <p class="small text-body-secondary mt-1">Track revenue, orders, and stock at a glance.</p>
    </div>

    {{-- Stats Cards --}}
    <div class="d-grid admin-cols-1 admin-cols-sm-2 admin-cols-lg-4 gap-4 mb-5">
        
        {{-- Total sales --}}
        <div class="card admin-kpi-card bg-white border border-light-subtle rounded-2 p-4 shadow-sm position-relative">
            <div class="admin-width-8 admin-height-8 position-absolute top-0 end-0 mt-3 me-3 rounded-2 bg-success-subtle text-success d-flex align-items-center justify-content-center fs-4"><svg class="admin-width-6 admin-height-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.2 0-4 1.1-4 2.5S9.8 13 12 13s4 1.1 4 2.5S14.2 18 12 18s-4-1.1-4-2.5M12 6v14M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <div>
                <span class="text-body-secondary admin-kpi-label">Total sales</span>
                <span class="admin-kpi text-body mt-1">{{ format_price($stats['total_sales']) }}</span>
            </div>
        </div>

        {{-- Today Sales --}}
        <div class="card admin-kpi-card bg-white border border-light-subtle rounded-2 p-4 shadow-sm position-relative">
            <div class="admin-width-8 admin-height-8 position-absolute top-0 end-0 mt-3 me-3 rounded-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center fs-4"><svg class="admin-width-6 admin-height-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19h16M5 15l5-5 4 3 6-8m-5 0h5v5"/></svg></div>
            <div>
                <span class="text-body-secondary admin-kpi-label">Today's sales</span>
                <span class="admin-kpi text-body mt-1">{{ format_price($stats['today_sales']) }}</span>
            </div>
        </div>

        {{-- Pending Orders --}}
        <div class="card admin-kpi-card bg-white border border-light-subtle rounded-2 p-4 shadow-sm position-relative">
            <div class="admin-width-8 admin-height-8 position-absolute top-0 end-0 mt-3 me-3 rounded-2 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center fs-4"><svg class="admin-width-6 admin-height-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5h14v14H5zM8 3v4m8-4v4M5 10h14m-7 3v3l2 1"/></svg></div>
            <div>
                <span class="text-body-secondary admin-kpi-label">Pending / total</span>
                <span class="admin-kpi text-body mt-1">
                    {{ $stats['pending_orders'] }} <span class="text-body-secondary small">/ {{ $stats['total_orders'] }}</span>
                </span>
            </div>
        </div>

        {{-- Low Stock Alert --}}
        <div class="card admin-kpi-card bg-white border border-light-subtle rounded-2 p-4 shadow-sm position-relative">
            <div class="admin-width-8 admin-height-8 position-absolute top-0 end-0 mt-3 me-3 rounded-2 bg-warning-subtle text-warning d-flex align-items-center justify-content-center fs-4"><svg class="admin-width-6 admin-height-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v5m0 3h.01M10.3 3.9L2.1 18.1A2 2 0 003.8 21h16.4a2 2 0 001.7-2.9L13.7 3.9a2 2 0 00-3.4 0z"/></svg></div>
            <div>
                <span class="text-body-secondary admin-kpi-label">Low stock products</span>
                <span class="admin-kpi text-body mt-1 {{ $stats['low_stock'] > 0 ? 'text-warning' : '' }}">
                    {{ $stats['low_stock'] }}
                </span>
            </div>
        </div>

    </div>

    {{-- Charts and Top Selling --}}
    <div class="d-grid admin-cols-1 admin-cols-lg-3 gap-5 mb-5">
        
        {{-- Revenue Chart --}}
        <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-span-lg-2 d-flex flex-column admin-height-96">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-light-subtle pb-2">
                <h3 class="text-body admin-section-title">7-day revenue trend (PKR)</h3>
            </div>
            <div class="flex-grow-1 admin-min-height-0 position-relative">
                <canvas id="revenueChart" class="w-100 h-100"></canvas>
            </div>
        </div>

        {{-- Top Selling Products --}}
        <div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm d-flex flex-column admin-height-96">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-light-subtle pb-2">
                <h3 class="text-body admin-section-title">Best sellers</h3>
            </div>
            <div class="flex-grow-1 overflow-y-auto admin-stack-3 pe-1">
                @forelse($topProducts as $p)
                    <div class="d-flex align-items-center gap-3">
                        <img src="{{ $p->main_image_url }}" alt="{{ $p->title }}" class="admin-width-10 admin-height-10 rounded-2 object-fit-cover flex-shrink-0">
                        <div class="admin-min-width-0 flex-grow-1">
                            <span class="fw-semibold text-body d-block text-truncate">{{ $p->title }}</span>
                            <span class="text-body-secondary d-block">{{ $p->category?->name }}</span>
                        </div>
                        <span class="small bg-body-tertiary text-body-secondary px-2 py-1 rounded-pill fw-semibold flex-shrink-0">x{{ $p->sales_count }}</span>
                    </div>
                @empty
                    <p class="small text-body-secondary text-center py-5">No sales data recorded.</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Recent Orders --}}
    <div id="admin-orders-live" class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm">
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-light-subtle pb-2">
            <h3 class="text-body admin-section-title">Recent orders</h3>
            <a href="{{ route('admin.orders.index') }}" class="small text-primary fw-semibold link-underline-opacity-100-hover">View all orders →</a>
        </div>

        @if($recentOrders->isEmpty())
            <p class="small text-body-secondary text-center py-5">No orders found.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-table small">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="price num">Total</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentOrders as $order)
                            <tr @class(['order-pending' => $order->status === 'pending'])>
                                <td class="text-body order-id">{{ $order->order_number }}</td>
                                <td>
                                    <span class="fw-medium text-body d-block">{{ $order->customer_name }}</span>
                                    <span class="text-body-secondary small">{{ $order->customer_phone }}</span>
                                </td>
                                <td class="text-body-secondary">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                                <td class="small fw-semibold">{{ $order->payment_method_label }}</td>
                                <td>
                                    <span class="admin-status-pill payment-status-{{ $order->payment_status }}">
                                        {{ ucfirst($order->payment_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-{{ $order->status }} small fw-semibold">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </td>
                                <td class="fw-semibold text-primary price num">{{ format_price($order->grand_total) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.orders.show', $order->id) }}" class="d-inline-flex align-items-center justify-content-center px-3 py-1 bg-body-tertiary small fw-semibold text-body rounded-2">
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

                        const tokens = getComputedStyle(document.documentElement);
                        const color = name => tokens.getPropertyValue(name).trim();
                        const ctx = document.getElementById('revenueChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Daily revenue (PKR)',
                                    data: revenues,
                                    borderColor: color('--chart-revenue'),
                                    backgroundColor: 'rgba(79, 70, 229, 0.08)',
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
                                            color: color('--border')
                                        },
                                        ticks: {
                                            color: color('--text-muted'),
                                            font: {
                                                size: 12,
                                                family: color('--font-ui')
                                            }
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            color: color('--text-muted'),
                                            font: {
                                                size: 12,
                                                family: color('--font-ui')
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

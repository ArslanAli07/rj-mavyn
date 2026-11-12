@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'Order History — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('account.dashboard') }}">My account</a><span>/</span><span aria-current="page">Order history</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-account">
                <x-account-nav active="orders" />

                <div class="rj-panel">
                    <div class="rj-panel__head">
                        <h2>Order history</h2>
                        <a class="rj-textlink" href="{{ route('shop') }}">Continue shopping <svg class="icon"><use href="#icon-arrow"/></svg></a>
                    </div>

                    @if($orders->isEmpty())
                        <div class="rj-empty" style="border-width:0">
                            <p><strong>No orders yet.</strong><br>When you are ready, our workshop takes your measurements from the checkout form.</p>
                            <p style="margin-top:14px"><a href="{{ route('shop') }}">Start shopping <svg class="icon" style="width:14px;height:14px;vertical-align:-2px"><use href="#icon-arrow"/></svg></a></p>
                        </div>
                    @else
                        <div class="rj-table-wrap" style="border:0">
                            <table class="rj-table">
                                <thead>
                                    <tr style="font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--rj-muted)">
                                        <th>Order number</th><th>Date</th><th>Payment</th><th>Status</th><th>Grand total</th><th style="text-align:right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($orders as $o)
                                        <tr>
                                            <td style="font-weight:700">{{ $o->order_number }}</td>
                                            <td style="color:var(--rj-muted)">{{ $o->created_at->format('M d, Y') }}</td>
                                            <td style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;color:var(--rj-muted)">{{ $o->payment_method }}</td>
                                            <td><span class="rj-chip rj-chip--outline">{{ str_replace('_', ' ', $o->status) }}</span></td>
                                            <td style="font-weight:700">{{ order_price($o, $o->grand_total) }}</td>
                                            <td style="text-align:right"><a class="rj-textlink" style="font-size:10px" href="{{ route('orders.show', $o->id) }}">View details</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $orders->links('components.pagination') }}
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-app-layout>

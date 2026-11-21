@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'My Account — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">My account</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-welcome">
                <div>
                    <span class="rj-chip rj-chip--accent">My account</span>
                    <h2 class="rj-display" style="margin-top:14px">Hello, {{ $user->name }}.</h2>
                    <p>Manage your details, addresses and orders &mdash; everything about your made-to-measure work lives here.</p>
                </div>
                <a class="rj-button rj-button--accent" href="{{ route('shop') }}">Browse the collection <svg class="icon"><use href="#icon-arrow"/></svg></a>
            </div>

            <div class="rj-account">
                <x-account-nav active="dashboard" />

                <div>
                    <div class="rj-stats">
                        <div class="rj-stat">
                            <span class="rj-eyebrow">Total orders</span>
                            <strong>{{ $totalOrders }}</strong>
                        </div>
                        <div class="rj-stat">
                            <span class="rj-eyebrow">Delivered spent</span>
                            <strong>@forelse($spentByCurrency as $code => $spent)<small style="font-size:15px;font-weight:400;color:var(--rj-muted)">{{ $code }}</small> {{ number_format((float) $spent, 0) }}@empty<small>{{ format_price(0) }}</small>@endforelse</strong>
                        </div>
                        <div class="rj-stat">
                            <span class="rj-eyebrow">Wishlist items</span>
                            <strong>{{ $wishlistCount }}</strong>
                        </div>
                    </div>

                    <div class="rj-panel">
                        <div class="rj-panel__head">
                            <h2>Recent orders</h2>
                            <a class="rj-textlink" href="{{ route('orders.index') }}">View all <svg class="icon"><use href="#icon-arrow"/></svg></a>
                        </div>

                        @if($orders->isEmpty())
                            <div class="rj-empty" style="border-width:0">
                                <p>
                                    <strong>No orders yet.</strong><br>
                                    Your first made-to-measure piece is one brief away.<br><br>
                                    <a href="{{ route('shop') }}">Shop now <svg class="icon" style="width:14px;height:14px;vertical-align:-2px"><use href="#icon-arrow"/></svg></a>
                                </p>
                            </div>
                        @else
                            <div class="rj-table-wrap" style="border:0">
                                <table class="rj-table">
                                    <thead>
                                        <tr style="font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--rj-muted)">
                                            <th>Order</th><th>Date</th><th>Payment</th><th>Status</th><th>Total</th><th style="text-align:right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($orders as $o)
                                            <tr style="font-size:12.5px">
                                                <td style="font-weight:700">{{ $o->order_number }}</td>
                                                <td style="color:var(--rj-muted)">{{ store_time($o->created_at)->format('M d, Y') }}</td>
                                                <td style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;color:var(--rj-muted)">{{ $o->payment_method_label }}</td>
                                                <td><span class="rj-chip rj-chip--outline">{{ str_replace('_', ' ', $o->status) }}</span></td>
                                                <td style="font-weight:700">{{ order_price($o, $o->grand_total) }}</td>
                                                <td style="text-align:right"><a class="rj-textlink" style="font-size:10px" href="{{ route('orders.show', $o->id) }}">Details</a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>

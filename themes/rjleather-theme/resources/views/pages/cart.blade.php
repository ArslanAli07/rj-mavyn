<x-app-layout title="Your bag — {{ setting('site_name', config('site.name', 'Store')) }}">
    <div class="rj-cart">
        <div class="rj-container">
            <x-breadcrumb :items="['Your bag' => route('cart.index')]" />

            <header class="rj-cart__head">
                <div>
                    <span class="rj-eyebrow">Your bag</span>
                    <h1 class="rj-display rj-title" style="margin-top:12px">Your bag</h1>
                </div>
                @if($items->isNotEmpty())
                    <p class="rj-lede" style="max-width:380px">Review your pieces before completing your order.</p>
                @endif
            </header>

            @if($items->isEmpty())
                <div class="rj-cart__empty">
                    <h2>Your bag is empty</h2>
                    <p>Time to pick a favourite piece.</p>
                    <a href="{{ route('shop') }}" class="rj-button">Browse the collection <svg class="icon"><use href="#icon-arrow"/></svg></a>
                </div>
            @else
                <div class="rj-cart__layout">
                    <div class="rj-cart__items">
                        @foreach($items as $item)
                            <div class="rj-cart__row">
                                <a class="rj-cart__thumb" href="{{ route('product.show', $item->product?->slug) }}" aria-label="View {{ $item->product?->title }}">
                                    <img src="{{ $item->product?->main_image_url ?? theme_asset('images/placeholder.svg') }}" alt="{{ $item->product?->title }}" loading="lazy">
                                </a>
                                <div>
                                    <a class="rj-cart__title" href="{{ route('product.show', $item->product?->slug) }}">{{ $item->product?->title }}</a>
                                    @if($item->product?->category)<p class="rj-cart__meta">{{ $item->product?->category?->name }}</p>@endif
                                    @include('components.item-configuration', [
                                        'item' => $item,
                                        'class' => 'rj-item-config',
                                        'labelClass' => 'rj-item-config__row',
                                        'optionClass' => 'rj-item-config__row',
                                        'titleClass' => 'rj-item-config__label',
                                        'adjustmentClass' => 'rj-item-config__row',
                                    ])
                                    <form action="{{ route('cart.remove', $item->id) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rj-cart__remove">Remove</button>
                                    </form>
                                </div>
                                <div class="rj-cart__quantity">
                                    <form action="{{ route('cart.update', $item->id) }}" method="POST" class="rj-cart__quantity">
                                        @csrf @method('PATCH')
                                        <button type="submit" name="quantity" value="{{ $item->quantity - 1 }}" aria-label="Decrease quantity of {{ $item->product?->title }}" {{ $item->quantity <= 1 ? 'disabled' : '' }}>&minus;</button>
                                        <span>{{ $item->quantity }}</span>
                                        <button type="submit" name="quantity" value="{{ $item->quantity + 1 }}" aria-label="Increase quantity of {{ $item->product?->title }}">+</button>
                                    </form>
                                </div>
                                <div class="rj-cart__price">{{ format_price($item->line_total) }}</div>
                            </div>
                        @endforeach
                    </div>

                    <aside class="rj-cart__summary">
                        <h2>Order summary</h2>
                        <div class="rj-cart__totals">
                            <div><span>Subtotal</span><strong>{{ format_price($totals['subtotal']) }}</strong></div>
                            @if($totals['discount'] > 0)
                                <div><span>Discount</span><strong>&minus;{{ format_price($totals['discount']) }}</strong></div>
                            @endif
                            <div>
                                <span>Delivery fee</span>
                                <strong>
                                    @if(is_null($totals['delivery_fee']))
                                        Depends on location
                                    @elseif($totals['delivery_fee'] > 0)
                                        {{ format_price($totals['delivery_fee']) }}
                                    @else
                                        Free
                                    @endif
                                </strong>
                            </div>
                        </div>

                        @if($coupon)
                            <div class="rj-cart__applied">
                                <span>Coupon &ldquo;{{ $coupon->code }}&rdquo; applied &mdash; you saved {{ format_price($totals['discount']) }}</span>
                                <form action="{{ route('cart.coupon.remove') }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rj-cart__remove">Remove</button>
                                </form>
                            </div>
                        @else
                            <form action="{{ route('cart.coupon') }}" method="POST" class="rj-coupon">
                                @csrf
                                <label class="sr-only" for="coupon-code">Promo or coupon code</label>
                                <input id="coupon-code" type="text" name="code" placeholder="Promo code" required>
                                <button type="submit" class="rj-button rj-button--sm">Apply</button>
                            </form>
                        @endif

                        @guest('web')
                            <p class="rj-cart__note">Guest coupon limits are checked using your checkout email and IP address.</p>
                        @endguest

                        <div class="rj-cart__total"><span>Total</span><span>{{ format_price($totals['grand_total']) }}</span></div>

                        <a href="{{ route('checkout.index') }}" class="rj-button rj-button--accent" style="width:100%;margin-top:24px">Proceed to checkout <svg class="icon"><use href="#icon-arrow"/></svg></a>
                    </aside>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

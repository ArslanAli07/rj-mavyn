@php
    /* RJ Leather account navigation. Neutral on purpose: the default theme's
       account templates keep their own sidebar, so this lives beside them. */
    $active = $active ?? 'dashboard';
    $user = auth()->user();
    $links = [
        ['dashboard', 'Dashboard', route('account.dashboard')],
        ['profile', 'Profile details', route('account.profile')],
        ['addresses', 'Address book', route('account.addresses')],
        ['orders', 'Order history', route('orders.index')],
        ['wishlist', 'My wishlist', route('account.wishlist')],
        ['password', 'Change password', route('account.password')],
    ];
@endphp
<aside class="rj-account__nav">
    <div class="rj-account__who">
        <span class="rj-account__monogram">{{ substr($user->name, 0, 1) }}</span>
        <span style="min-width:0">
            <strong>{{ $user->name }}</strong>
            <span class="rj-account__email">{{ $user->email }}</span>
        </span>
    </div>
    @foreach($links as [$key, $label, $url])
        <a href="{{ $url }}" @if($active === $key) class="is-active" aria-current="page" @endif>{{ $label }}</a>
    @endforeach

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="rj-account__logout">Log out</button>
    </form>
</aside>

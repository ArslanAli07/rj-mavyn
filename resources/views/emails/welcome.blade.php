@extends('emails.layout')

@section('title', 'Welcome to '.($brand ?? \App\Services\StoreIdentity::snapshot())['name'].'!')

@section('content')
    <div class="welcome">Welcome to the Family, {{ $user->name }}!</div>
    <p class="intro">
        Thank you for creating an account with {{ ($brand ?? \App\Services\StoreIdentity::snapshot())['name'] }}. You can now browse our products, place orders, and manage your account.
    </p>

    <div class="features-box">
        <div class="feature-item">
            <span class="feature-title">Order Online:</span> Browse our products and checkout in seconds.
        </div>
        <div class="feature-item">
            <span class="feature-title">🚚 Fast Delivery:</span> Choose from the available fulfillment options at checkout.
        </div>
        <div class="feature-item">
            <span class="feature-title">📍 Saved Addresses:</span> Save multiple shipping addresses for a faster checkout.
        </div>
        <div class="feature-item">
            <span class="feature-title">🕒 Order History:</span> Track active orders and view past receipts easily.
        </div>
    </div>

    <center>
        <a href="{{ route('shop') }}" class="button">
            Explore the Shop
        </a>
    </center>
@endsection

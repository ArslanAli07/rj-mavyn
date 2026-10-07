@extends('emails.layout')

@section('title', 'Welcome to Happy Tea!')

@section('content')
    <div class="welcome">Welcome to the Family, {{ $user->name }}!</div>
    <p class="intro">
        Thank you for creating an account with Happy Tea. We're absolutely thrilled to have you here! Your account is now active, and you can start enjoying a seamless ordering experience for coffee, tea, and frozen treats.
    </p>

    <div class="features-box">
        <div class="feature-item">
            <span class="feature-title">🧋 Order Online:</span> Browse our full menu and checkout in seconds.
        </div>
        <div class="feature-item">
            <span class="feature-title">🚚 Fast Delivery:</span> Get your favorite drinks and treats delivered fresh to your door.
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

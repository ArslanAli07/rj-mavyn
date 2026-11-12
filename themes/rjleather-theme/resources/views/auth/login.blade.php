@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'Sign In — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <div class="rj-auth">
            <header class="rj-auth__head">
                <span class="rj-eyebrow">{{ $brand }}</span>
                <h1 class="rj-display rj-title">Welcome back</h1>
                <p class="rj-lede">Sign in to place an order, track deliveries and manage your account.</p>
            </header>

            @if(session('status'))
                <div class="rj-flash" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="rj-form">
                @csrf
                <x-honeypot />

                <label>
                    Email address *
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                    @error('email')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    Password *
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                    @error('password')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <label class="rj-checkbox">
                    <input id="remember_me" type="checkbox" name="remember">
                    <span>Remember me</span>
                </label>

                <button type="submit" class="rj-button">Sign in <svg class="icon"><use href="#icon-arrow"/></svg></button>

                <div class="rj-form__foot">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                    <span>New here? <a href="{{ route('register') }}">Create an account</a></span>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'Forgot Password — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <div class="rj-auth">
            <header class="rj-auth__head">
                <span class="rj-eyebrow">{{ $brand }}</span>
                <h1 class="rj-display rj-title">Forgot password</h1>
                <p class="rj-lede">Enter your email and we will send you a secure link to reset your password.</p>
            </header>

            @if(session('status'))
                <div class="rj-flash" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="rj-form">
                @csrf
                <x-honeypot />

                <label>
                    Email address *
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <button type="submit" class="rj-button">Email reset link <svg class="icon"><use href="#icon-arrow"/></svg></button>

                <div class="rj-form__foot">
                    <span>Remembered it? <a href="{{ route('login') }}">Sign in</a></span>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'Create Account — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <div class="rj-auth">
            <header class="rj-auth__head">
                <span class="rj-eyebrow">{{ $brand }}</span>
                <h1 class="rj-display rj-title">Create your account</h1>
                <p class="rj-lede">Join our store to save favourites, track orders and check out faster.</p>
            </header>

            <form method="POST" action="{{ route('register') }}" class="rj-form">
                @csrf
                <x-honeypot />

                <label>
                    Full name *
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                    @error('name')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    Email address *
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
                    @error('email')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    Password *
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    @error('password')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    Confirm password *
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                    @error('password_confirmation')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <button type="submit" class="rj-button">Create account <svg class="icon"><use href="#icon-arrow"/></svg></button>

                <div class="rj-form__foot">
                    <span>Already registered? <a href="{{ route('login') }}">Sign in</a></span>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'Reset Password — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <div class="rj-auth">
            <header class="rj-auth__head">
                <span class="rj-eyebrow">{{ $brand }}</span>
                <h1 class="rj-display rj-title">Reset password</h1>
                <p class="rj-lede">Create a new secure password for your account.</p>
            </header>

            <form method="POST" action="{{ route('password.store') }}" class="rj-form">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <label>
                    Email address *
                    <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
                    @error('email')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    New password *
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    @error('password')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <label>
                    Confirm password *
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                    @error('password_confirmation')<span class="rj-error">{{ $message }}</span>@enderror
                </label>

                <button type="submit" class="rj-button">Reset password <svg class="icon"><use href="#icon-arrow"/></svg></button>
            </form>
        </div>
    </div>
</x-app-layout>

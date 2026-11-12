@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'Change Password — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('account.dashboard') }}">My account</a><span>/</span><span aria-current="page">Change password</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-account">
                <x-account-nav active="password" />

                <div class="rj-panel">
                    <div class="rj-panel__head"><h2>Change password</h2></div>

                    <form action="{{ route('account.password.update') }}" method="POST" class="rj-form" style="max-width:440px">
                        @csrf
                        @method('PUT')

                        <label>
                            Current password *
                            <input id="current_password" type="password" name="current_password" required>
                            @error('current_password')<span class="rj-error">{{ $message }}</span>@enderror
                        </label>

                        <label>
                            New password *
                            <input id="password" type="password" name="password" required>
                            @error('password')<span class="rj-error">{{ $message }}</span>@enderror
                        </label>

                        <label>
                            Confirm new password *
                            <input id="password_confirmation" type="password" name="password_confirmation" required>
                            @error('password_confirmation')<span class="rj-error">{{ $message }}</span>@enderror
                        </label>

                        <div><button type="submit" class="rj-button">Update password <svg class="icon"><use href="#icon-arrow"/></svg></button></div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>

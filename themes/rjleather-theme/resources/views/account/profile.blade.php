@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
@endphp
<x-app-layout :title="'Profile Settings — '.setting('site_name', config('site.name', 'Store'))">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('account.dashboard') }}">My account</a><span>/</span><span aria-current="page">Profile details</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-account">
                <x-account-nav active="profile" />

                <div class="rj-panel">
                    <div class="rj-panel__head"><h2>Profile details</h2></div>

                    @if(session('success'))
                        <div class="rj-flash" style="margin-bottom:22px">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('account.profile.update') }}" method="POST" class="rj-form" style="max-width:560px">
                        @csrf
                        @method('PUT')

                        <label>
                            Full name *
                            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')<span class="rj-error">{{ $message }}</span>@enderror
                        </label>

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">
                            <label>
                                Email address
                                <input id="email" type="email" value="{{ old('email', $user->email) }}" readonly style="background:var(--rj-surface);color:var(--rj-muted)">
                                <span style="text-transform:none;letter-spacing:0;font-weight:400;font-size:11px;color:var(--rj-muted)">Contact customer support to update your email.</span>
                            </label>
                            <label>
                                Phone number
                                <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 03001234567">
                                @error('phone')<span class="rj-error">{{ $message }}</span>@enderror
                            </label>
                        </div>

                        <div><button type="submit" class="rj-button">Save changes <svg class="icon"><use href="#icon-arrow"/></svg></button></div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>

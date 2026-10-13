<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-100 bg-body-tertiary">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ store_media_url('site_favicon', asset('assets/favicon-32x32.png')) }}">

    <title>{{ $title ?? 'Welcome' }} — {{ setting('site_name', 'Store') }}</title>

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    @stack('head')
</head>
<body class="h-100 text-body d-flex align-items-center justify-content-center p-3 admin-auth-background position-relative overflow-hidden">

    <div class="position-fixed top-0 bottom-0 start-0 end-0 overflow-hidden pe-none" aria-hidden="true">
        <div class="position-absolute admin-width-96 admin-height-96 bg-primary-subtle rounded-pill admin-decoration opacity-50"></div>
        <div class="position-absolute admin-width-96 admin-height-96 bg-body-tertiary rounded-pill admin-decoration opacity-25"></div>
    </div>

    <div class="card w-100 admin-max-width-md bg-white rounded-2 shadow border border-light-subtle overflow-hidden position-relative z-1">
        
        {{-- Card Header --}}
        <div class="px-5 pt-5 pb-3 text-center flex-shrink-0">
            <a href="{{ route('home') }}" class="d-inline-flex flex-column lh-1 mb-3">
                <span class="admin-heading fw-semibold fs-3 text-body">{{ setting('site_name', 'Store') }}</span>
                <span class="small text-body-secondary admin-letter-spacing fw-normal mt-1">{{ setting('site_tagline', '') }}</span>
            </a>
            <div class="admin-height-0-5 admin-brand-divider admin-width-24 mx-auto mb-2"></div>
        </div>

        {{-- Slot content --}}
        <div class="px-5 pb-5 pt-2">
            {{ $slot }}
        </div>
    </div>

</body>
</html>

@props(['items' => []])
@if($iconsOnly ?? false)
<svg xmlns="http://www.w3.org/2000/svg" class="icon-definitions" aria-hidden="true">
    <symbol id="icon-arrow" viewBox="0 0 24 24"><path d="M3 12h17M14 6l6 6-6 6"/></symbol>
    <symbol id="icon-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="icon-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></symbol>
    <symbol id="icon-heart" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0l-1 1-1-1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></symbol>
    <symbol id="icon-user" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/></symbol>
    <symbol id="icon-bag" viewBox="0 0 24 24"><path d="M4 7h16l1 14H3L4 7ZM8 8V6a4 4 0 0 1 8 0v2"/></symbol>
    <symbol id="icon-menu" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></symbol>
    <symbol id="icon-close" viewBox="0 0 24 24"><path d="m5 5 14 14M19 5 5 19"/></symbol>
    <symbol id="icon-filter" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18M7 3v6M17 9v6M10 15v6"/></symbol>
    <symbol id="icon-check" viewBox="0 0 24 24"><path d="m4 12 5 5L20 6"/></symbol>
    <symbol id="icon-truck" viewBox="0 0 24 24"><path d="M1 5h13v13H1V5ZM14 10h4l5 5v3h-9"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/></symbol>
    <symbol id="icon-return" viewBox="0 0 24 24"><path d="m8 4-5 5 5 5M3 9h11a7 7 0 0 1 0 14"/></symbol>
    <symbol id="icon-ruler" viewBox="0 0 24 24"><path d="M3 7h18v10H3V7ZM7 7v5M11 7v3M15 7v5M19 7v3"/></symbol>
    <symbol id="icon-needle" viewBox="0 0 24 24"><path d="m3 21 9-9M12 12l8-8M17 3l4 4M9.5 14.5c-2 0-3.5 1-3.5 3.5 2.5 0 3.5-1.5 3.5-3.5Z"/></symbol>
    <symbol id="icon-star" viewBox="0 0 24 24"><path d="m12 2 3 6.5 7 1-5 5 .9 7L12 18.2l-5.9 3.3.9-7-5-5 7-1L12 2Z"/></symbol>
    <symbol id="icon-plus" viewBox="0 0 24 24"><path d="M12 4v16M4 12h16"/></symbol>
    <symbol id="icon-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
    <symbol id="icon-location" viewBox="0 0 24 24"><path d="M19 10c0 5-7 12-7 12S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2"/></symbol>
    <symbol id="icon-phone" viewBox="0 0 24 24"><path d="M6.5 3h3l1.5 4-2 1.5a12 12 0 0 0 6.5 6.5L17 13l4 1.5v3a2.5 2.5 0 0 1-2.7 2.5A16.5 16.5 0 0 1 4 6.7 2.5 2.5 0 0 1 6.5 3Z"/></symbol>
    <symbol id="icon-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></symbol>
    <symbol id="icon-facebook" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M13.6 21v-7.9h2.7l.4-3.1h-3.1V8c0-.9.3-1.5 1.6-1.5h1.6V3.7a22 22 0 0 0-2.3-.1c-2.4 0-4 1.5-4 4.1V10H7.7v3.1h2.7V21h3.2Z"/></symbol>
    <symbol id="icon-instagram" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></symbol>
    <symbol id="icon-tiktok" viewBox="0 0 24 24"><path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 6.5A5.5 5.5 0 0 0 19.5 3"/></symbol>
    <symbol id="icon-whatsapp" viewBox="0 0 24 24"><path d="M3.5 20.5 5 16.6A8 8 0 1 1 8.2 19.6Z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5.7 0 1.2-.5 1.2-1.1 0-.4-.6-1-1-1-.3 0-.5.2-.6.4-.9-.3-1.8-1.2-2.1-2.1.2-.1.4-.3.4-.6 0-.4-.6-1-1-1-.6 0-1.1.5-1.1 1.2"/></symbol>
</svg>
@else
<nav class="rj-breadcrumb rj-container" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a>@foreach($items as $label => $url)@php $cleanLabel = preg_replace('/^for\s+/i', '', $label); @endphp<span>/</span>@if($url)<a href="{{ $url }}">{{ $cleanLabel }}</a>@else<span aria-current="page">{{ $cleanLabel }}</span>@endif @endforeach</nav>
@endif

@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
    $contactAddress = trim((string) setting('site_address'));
    $contactPhone = trim((string) setting('site_phone'));
    $contactEmail = trim((string) setting('site_email', config('site.email')));
    $contactWhatsapp = trim((string) setting('site_whatsapp'));
    $contactWhatsappDigits = preg_replace('/[^0-9]/', '', $contactWhatsapp);
@endphp
<x-app-layout :title="'Contact | '.setting('site_name', config('site.name', 'Store')).' — Custom Orders & Support'" :meta-description="'Questions about a made-to-measure leather jacket, an order or delivery? Contact the '.setting('site_name', config('site.name', 'RJ Motorbike')).' workshop by email, phone or WhatsApp.'" page-key="contact">
    @include('guide._partials', ['noTabs' => true])
    <div class="gd ct">
        <div class="rj-container">
            <nav class="rj-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">Contact</span>
            </nav>

            <header class="gd-head gd-head--center">
                <h1 class="gd-title"><span>Contact us</span></h1>
                <p class="gd-lede">Questions about a piece, a made-to-measure brief, your order or delivery? Write to us and the workshop will answer within 24 hours.</p>
            </header>

            @if(session('success'))
                <div class="rj-flash" role="status" style="margin:22px 0 0">{{ session('success') }}</div>
            @endif

            <div class="ct-cards">
                @if($contactEmail)
                    <a class="ct-card" href="mailto:{{ $contactEmail }}">
                        <svg class="icon" aria-hidden="true"><use href="#icon-mail"/></svg>
                        <strong>Email</strong><span>{{ $contactEmail }}</span>
                    </a>
                @endif
                @if($contactWhatsappDigits)
                    <a class="ct-card" href="https://wa.me/{{ $contactWhatsappDigits }}" target="_blank" rel="noopener">
                        <svg class="icon" aria-hidden="true"><use href="#icon-whatsapp"/></svg>
                        <strong>WhatsApp</strong><span>Chat now ({{ $contactWhatsapp }})</span>
                    </a>
                @endif
                @if($contactPhone)
                    <a class="ct-card" href="tel:{{ $contactPhone }}">
                        <svg class="icon" aria-hidden="true"><use href="#icon-phone"/></svg>
                        <strong>Phone</strong><span>{{ $contactPhone }}</span>
                    </a>
                @endif
                @if($contactAddress)
                    <div class="ct-card">
                        <svg class="icon" aria-hidden="true"><use href="#icon-location"/></svg>
                        <strong>Workshop</strong><span>{{ $contactAddress }}</span>
                    </div>
                @endif
            </div>

            <div class="ct-main">
                <div class="ct-form">
                    <h2 class="gd-h2">Send us a message</h2>
                    <p class="ct-sub">Tell us the piece, the leather, the finish. We reply with options, pricing and timelines.</p>
                    <form action="{{ route('contact.send') }}" method="POST" class="rj-form">
                                @csrf
                                <x-honeypot />
        
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
                                    <label>
                                        Full name *
                                        <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Your name" required>
                                        @error('name')<span class="rj-error">{{ $message }}</span>@enderror
                                    </label>
                                    <label>
                                        Email address *
                                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
                                        @error('email')<span class="rj-error">{{ $message }}</span>@enderror
                                    </label>
                                </div>
        
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px">
                                    <label>
                                        Phone number (optional)
                                        <input id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="e.g. 03001234567">
                                        @error('phone')<span class="rj-error">{{ $message }}</span>@enderror
                                    </label>
                                    <label>
                                        Subject *
                                        <input id="subject" type="text" name="subject" value="{{ old('subject') }}" placeholder="e.g. Made-to-measure jacket" required>
                                        @error('subject')<span class="rj-error">{{ $message }}</span>@enderror
                                    </label>
                                </div>
        
                                <label>
                                    Message *
                                    <textarea id="message" name="message" rows="6" required placeholder="Tell us what you are after &mdash; details, deadlines, anything that shapes the piece.">{{ old('message') }}</textarea>
                                    @error('message')<span class="rj-error">{{ $message }}</span>@enderror
                                </label>
        
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                                    <button type="submit" class="rj-button rj-button--accent">Send message <svg class="icon"><use href="#icon-arrow"/></svg></button>
                                    @if($contactWhatsappDigits)
                                        <a class="rj-textlink" href="https://wa.me/{{ $contactWhatsappDigits }}" target="_blank" rel="noopener">Or chat on WhatsApp <svg class="icon"><use href="#icon-arrow"/></svg></a>
                                    @endif
                                </div>
                            </form>
                </div>

                <aside class="ct-help">
                    <h2 class="gd-h2">Quick answers</h2>
                    <ul>
                        <li><a href="{{ route('guide.how-it-works') }}">How ordering works</a><span>Sizes, production time and delivery</span></li>
                        <li><a href="{{ route('guide.size-guide') }}">Size guide</a><span>Find your standard size</span></li>
                        <li><a href="{{ route('guide.how-to-measure') }}">How to measure</a><span>For Custom Size and Custom Made</span></li>
                        <li><a href="{{ route('shipping') }}">Shipping policy</a><span>Costs, carriers and tracking</span></li>
                        <li><a href="{{ route('returns') }}">Refund policy</a><span>Returns, faults and refunds</span></li>
                        <li><a href="{{ route('faq') }}">FAQs</a><span>Answers to common questions</span></li>
                    </ul>
                    <p class="ct-note">Already ordered? Include your order number so we can find it quickly.</p>
                </aside>
            </div>
        </div>
    </div>
    <style>
        .ct {margin-bottom:64px;}
        .ct-cards:not(:has(.ct-card)) {display:none;}
        .ct-cards {display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin:34px 0 8px;}
        .ct-card {display:flex;flex-direction:column;gap:6px;padding:22px;border:1px solid var(--gd-line);background:#fff;color:var(--gd-ink);text-decoration:none;font-size:14px;line-height:1.55;}
        a.ct-card:hover {border-color:var(--gd-ink);}
        .ct-card .icon {width:22px;height:22px;margin-bottom:4px;}
        .ct-card strong {font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;}
        .ct-card span {color:#3a3532;overflow-wrap:anywhere;}
        .ct-card--hours {gap:4px;}
        .ct-cards > .ct-card--hours:only-child {max-width:420px;}
        .ct-card--hours strong {margin-bottom:6px;}
        .ct-card--hours div {display:flex;justify-content:space-between;gap:12px;font-size:13px;color:#3a3532;}
        .ct-card--hours div span:first-child {font-weight:700;}
        .ct-main {display:grid;gap:48px;grid-template-columns:minmax(0,1.7fr) minmax(0,1fr);align-items:start;margin-top:38px;padding-top:34px;border-top:1px solid var(--gd-line);}
        .ct-sub {margin:-6px 0 22px;color:#3a3532;line-height:1.6;font-size:15px;}
        .ct .rj-form {display:grid;gap:18px;}
        .ct .rj-form label {display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;}
        .ct .rj-form input,.ct .rj-form textarea {width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid var(--gd-line);background:#fff;font:inherit;font-size:14px;text-transform:none;letter-spacing:0;font-weight:400;color:var(--gd-ink);}
        .ct .rj-form input {height:46px;}
        .ct .rj-form textarea {min-height:150px;resize:vertical;}
        .ct .rj-form input:focus,.ct .rj-form textarea:focus {outline:2px solid var(--gd-ink);outline-offset:0;}
        .ct .rj-form button[type=submit] {display:inline-flex;align-items:center;gap:10px;min-height:46px;padding:0 28px;border:1px solid var(--gd-ink);border-radius:100px;background:var(--gd-ink);color:#fff;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;}
        .ct .rj-form button .icon {width:14px;height:14px;}
        .ct-help ul {list-style:none;margin:0;padding:0;border-top:1px solid var(--gd-line);}
        .ct-help li {padding:14px 0;border-bottom:1px solid var(--gd-line);display:flex;flex-direction:column;gap:2px;}
        .ct-help li a {font-weight:800;font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:var(--gd-ink);text-decoration:none;}
        .ct-help li span {font-size:13px;color:var(--gd-muted);}
        .ct-note {margin:18px 0 0;padding:16px 18px;background:var(--gd-soft);font-size:14px;line-height:1.6;}
        @media(max-width:860px){.ct-main {grid-template-columns:1fr;gap:34px;}}
    </style>
</x-app-layout>

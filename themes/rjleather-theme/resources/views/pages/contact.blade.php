@php
    $brand = theme_setting('brand_name', 'RJ Motorbike');
    $contactAddress = trim((string) setting('site_address'));
    $contactPhone = trim((string) setting('site_phone'));
    $contactEmail = trim((string) setting('site_email'));
    $contactWhatsapp = trim((string) setting('site_whatsapp'));
    $contactWhatsappDigits = preg_replace('/[^0-9]/', '', $contactWhatsapp);
@endphp
<x-app-layout :title="'Contact | '.setting('site_name', config('site.name', 'Store')).' — Custom Orders & Support'" :meta-description="'Questions about a made-to-measure leather jacket, an order or delivery? Contact the '.setting('site_name', config('site.name', 'RJ Motorbike')).' workshop by email, phone or WhatsApp.'" page-key="contact">
    <div class="rj-container">
        <nav class="rj-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">Contact</span>
        </nav>

        <section class="rj-section rj-section--tight" style="padding-bottom:80px">
            <div class="rj-section-head">
                <div class="rj-section-head__copy">
                    <span class="rj-eyebrow">{{ $brand }} &mdash; customer care</span>
                    <h1 class="rj-display rj-title">Get in touch</h1>
                    <p class="rj-lede">Questions about the collection, a made-to-measure brief, an order or delivery timings &mdash; write to us and the workshop will answer.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="rj-flash" role="status" style="margin-bottom:26px">{{ session('success') }}</div>
            @endif

            <div class="rj-contact">
                {{-- Contact details: every available channel, then opening hours --}}
                <aside class="rj-contact__panel">
                    <h2>Contact details</h2>

                    @if($contactAddress)
                        <p class="rj-contact__item">
                            <svg class="icon" aria-hidden="true"><use href="#icon-location"/></svg>
                            <span><strong>Workshop &amp; store</strong>{{ $contactAddress }}</span>
                        </p>
                    @endif

                    @if($contactPhone)
                        <p class="rj-contact__item">
                            <svg class="icon" aria-hidden="true"><use href="#icon-phone"/></svg>
                            <span><strong>Phone</strong><a href="tel:{{ $contactPhone }}">{{ $contactPhone }}</a></span>
                        </p>
                    @endif

                    @if($contactEmail)
                        <p class="rj-contact__item">
                            <svg class="icon" aria-hidden="true"><use href="#icon-mail"/></svg>
                            <span><strong>Email</strong><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></span>
                        </p>
                    @endif

                    @if($contactWhatsappDigits)
                        <p class="rj-contact__item">
                            <svg class="icon" aria-hidden="true"><use href="#icon-whatsapp"/></svg>
                            <span><strong>WhatsApp orders</strong><a href="https://wa.me/{{ $contactWhatsappDigits }}" target="_blank" rel="noopener">Chat now ({{ $contactWhatsapp }})</a></span>
                        </p>
                    @endif

                    <div class="rj-contact__hours">
                        <h2 style="margin-bottom:4px">Opening hours</h2>
                        @foreach(config('site.business_hours') as $days => $hours)
                            <div><span>{{ $days }}</span><span>{{ $hours }}</span></div>
                        @endforeach
                    </div>
                </aside>

                {{-- Inquiry form --}}
                <div class="rj-contact__form-card">
                    <h2>Send an inquiry</h2>
                    <p class="rj-lede" style="max-width:none">Tell us the brief: the piece, the leather, the finish. We reply with options, pricing and timelines.</p>

                    <form action="{{ route('contact.send') }}" method="POST" class="rj-form" style="margin-top:26px">
                        @csrf
                        <x-honeypot />

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">
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

                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">
                            <label>
                                Phone number <span style="text-transform:none;font-weight:400;letter-spacing:0">(optional)</span>
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
            </div>
        </section>
    </div>
</x-app-layout>

<x-app-layout title="Contact Us — Happy Tea" page-key="contact">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="['Contact' => '']" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        
        {{-- Title --}}
        <div class="text-center max-w-xl mx-auto mb-16 animate-fade-up">
            <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">Get in Touch</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
            <p class="text-sm text-espresso-500 font-light leading-relaxed">
                Have a question about our menu, an order, or delivery timings? We would love to hear from you. Drop us a line or message us directly on WhatsApp.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            {{-- Contact Information --}}
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
                    <h3 class="font-heading font-bold text-lg text-espresso-800 border-b border-cream-100 pb-3 mb-4">Contact Details</h3>

                    <div class="space-y-4 text-xs sm:text-sm text-espresso-700">
                        @php
                            $contactAddress = trim((string) setting('site_address'));
                            $contactPhone = trim((string) setting('site_phone'));
                            $contactEmail = trim((string) setting('site_email'));
                            $contactWhatsapp = trim((string) setting('site_whatsapp'));
                            $contactWhatsappDigits = preg_replace('/[^0-9]/', '', $contactWhatsapp);
                        @endphp

                        {{-- Address --}}
                        @if($contactAddress)
                        <div class="flex items-start gap-3">
                            <span class="text-xl shrink-0">📍</span>
                            <div class="space-y-0.5">
                                <span class="font-bold text-espresso-855 block">Café Location</span>
                                <span class="text-espresso-550 font-light leading-relaxed">{{ $contactAddress }}</span>
                            </div>
                        </div>
                        @endif

                        {{-- Phone --}}
                        @if($contactPhone)
                        <div class="flex items-start gap-3">
                            <span class="text-xl shrink-0">📞</span>
                            <div class="space-y-0.5">
                                <span class="font-bold text-espresso-855 block">Phone Number</span>
                                <a href="tel:{{ $contactPhone }}" class="text-espresso-550 font-light hover:text-primary-600 transition-colors">{{ $contactPhone }}</a>
                            </div>
                        </div>
                        @endif

                        {{-- Email --}}
                        @if($contactEmail)
                        <div class="flex items-start gap-3">
                            <span class="text-xl shrink-0">✉️</span>
                            <div class="space-y-0.5">
                                <span class="font-bold text-espresso-855 block">Email Address</span>
                                <a href="mailto:{{ $contactEmail }}" class="text-espresso-550 font-light hover:text-primary-600 transition-colors">{{ $contactEmail }}</a>
                            </div>
                        </div>
                        @endif

                        {{-- WhatsApp --}}
                        @if($contactWhatsappDigits)
                        <div class="flex items-start gap-3">
                            <span class="text-xl shrink-0">💬</span>
                            <div class="space-y-0.5">
                                <span class="font-bold text-espresso-855 block">WhatsApp Orders</span>
                                <a href="https://wa.me/{{ $contactWhatsappDigits }}" target="_blank" rel="noopener" class="text-green-600 font-bold hover:underline">
                                    Chat Now ({{ $contactWhatsapp }})
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>

                    <hr class="border-cream-100">

                    {{-- Business Hours --}}
                    <div>
                        <h4 class="font-heading font-bold text-sm text-espresso-850 mb-3">Café Hours</h4>
                        <div class="space-y-2 text-xs">
                            @foreach(config('site.business_hours') as $days => $hours)
                                <div class="flex justify-between text-espresso-700">
                                    <span class="font-medium">{{ $days }}:</span>
                                    <span class="text-espresso-500">{{ $hours }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Interactive Contact Form --}}
            <div class="lg:col-span-7 bg-white border border-cream-150 rounded-3xl p-6 sm:p-8 shadow-sm">
                <h3 class="font-heading font-bold text-lg text-espresso-800 border-b border-cream-100 pb-3 mb-6">Send an Inquiry</h3>

                <form action="{{ route('contact.send') }}" method="POST" class="space-y-4">
                    @csrf
                    <x-honeypot />
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="label-luxury">Full Name</label>
                            <input type="text" id="name" name="name" placeholder="John Doe" required class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="email" class="label-luxury">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="john@example.com" required class="input-luxury text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="label-luxury">Phone Number (Optional)</label>
                            <input type="text" id="phone" name="phone" placeholder="e.g. 03001234567" class="input-luxury text-sm">
                        </div>
                        <div>
                            <label for="subject" class="label-luxury">Subject</label>
                            <input type="text" id="subject" name="subject" placeholder="e.g. Catering order inquiry" required class="input-luxury text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="message" class="label-luxury">Your Message / Requirements</label>
                        <textarea id="message" name="message" rows="5" placeholder="Tell us how we can help — menu questions, delivery preferences, or group orders..." required class="input-luxury text-sm"></textarea>
                    </div>

                    <button type="submit" class="btn-gold px-8 py-3 text-sm font-semibold tracking-wide">
                        Send Message
                    </button>
                </form>
            </div>

        </div>

    </div>

</x-app-layout>

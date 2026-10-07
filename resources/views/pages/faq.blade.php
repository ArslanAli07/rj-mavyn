<x-app-layout title="Frequently Asked Questions — Happy Tea" page-key="faq">

    {{-- Breadcrumb --}}
    <x-breadcrumb :items="['FAQ' => '']" />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-16" x-data="{ activeFaq: null }">
        
        {{-- Title --}}
        <div class="text-center max-w-xl mx-auto mb-16 animate-fade-up">
            <h1 class="text-3xl sm:text-4xl font-heading font-bold text-espresso-800">Frequently Asked Questions</h1>
            <div class="gold-line w-16 mx-auto mt-2 mb-4"></div>
            <p class="text-sm text-espresso-500 font-light leading-relaxed">
                Got questions about ordering, delivery areas, or payment steps? Check out our quick answers below.
            </p>
        </div>

        {{-- Categories and Accordions --}}
        @if($faqs->isEmpty())
            <div class="bg-white border border-cream-150 rounded-3xl p-12 text-center">
                <div class="text-5xl mb-4">❓</div>
                <h2 class="font-heading text-lg font-bold text-espresso-800">No FAQs available</h2>
                <p class="text-xs text-espresso-500 mt-2 mb-6">Our FAQs are currently being updated. Please contact us on WhatsApp for assistance.</p>
                <a href="{{ route('contact') }}" class="btn-primary">Contact Support</a>
            </div>
        @else
            <div class="space-y-12">
                @foreach($faqs as $category => $items)
                    <div class="space-y-4">
                        {{-- Category title --}}
                        <h2 class="font-heading font-bold text-xl text-espresso-800 border-b border-cream-200 pb-2.5 uppercase tracking-wide text-sm">{{ $category }}</h2>
                        
                        {{-- FAQ Items --}}
                        <div class="space-y-3">
                            @foreach($items as $faq)
                                <div class="bg-white border border-cream-150 rounded-2xl overflow-hidden transition-all duration-300"
                                     :class="activeFaq === {{ $faq->id }} ? 'shadow-md ring-1 ring-primary-300' : ''">
                                    
                                    {{-- Question Toggle --}}
                                    <button type="button" @click="activeFaq = (activeFaq === {{ $faq->id }} ? null : {{ $faq->id }})"
                                            class="w-full flex items-center justify-between text-left px-5 py-4 font-semibold text-espresso-800 text-sm focus:outline-none select-none">
                                        <span>{{ $faq->question }}</span>
                                        <svg class="w-4 h-4 text-primary-500 transition-transform duration-300 shrink-0 ml-4"
                                             :class="activeFaq === {{ $faq->id }} ? 'rotate-180' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    {{-- Answer Body --}}
                                    <div x-show="activeFaq === {{ $faq->id }}" x-cloak x-collapse
                                         x-transition:enter="transition-all ease-out duration-300"
                                         x-transition:leave="transition-all ease-in duration-200"
                                         class="px-5 pb-5 pt-1 text-xs sm:text-sm text-espresso-650 leading-relaxed font-light border-t border-cream-50 bg-cream-50/20">
                                        {!! nl2br(e($faq->answer)) !!}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Contact banner --}}
        @php
            $faqWhatsappDigits = preg_replace('/[^0-9]/', '', (string) setting('site_whatsapp'));
        @endphp
        @if($faqWhatsappDigits)
        <div class="bg-cream-100 border border-cream-200 rounded-3xl p-8 text-center mt-16 space-y-4">
            <h3 class="font-heading font-bold text-lg text-espresso-800">Still have questions?</h3>
            <p class="text-xs sm:text-sm text-espresso-600 font-light max-w-md mx-auto leading-relaxed">
                If you cannot find what you are looking for, our friendly customer success team is ready to assist you on WhatsApp.
            </p>
            <a href="https://wa.me/{{ $faqWhatsappDigits }}" target="_blank" rel="noopener"
               class="btn-gold bg-green-600 hover:bg-green-700 inline-flex items-center gap-2 py-2.5 px-6 text-xs font-semibold">
                Chat with Us on WhatsApp
            </a>
        </div>
        @endif

    </div>

</x-app-layout>

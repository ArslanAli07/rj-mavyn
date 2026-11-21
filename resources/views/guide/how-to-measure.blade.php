@php($store = setting('site_name', config('site.name', 'RJ Motorbike')))
<x-app-layout :title="'How to Measure — '.$store" :meta-description="'How to take your body measurements for a '.$store.' Custom Size or custom-made race suit, jacket or pant.'" page-key="how-to-measure">
    <div class="gd">
        <div class="rj-container">
            <nav class="rj-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('guide.how-it-works') }}">How it works</a><span>/</span><span aria-current="page">How to measure</span>
            </nav>

            @include('guide._partials', ['noTabs' => true])
            <header class="gd-head gd-head--center">
                <h1 class="gd-title"><span>How to measure</span></h1>
                <p class="gd-lede">For a Custom Size or Custom Made order we cut the pattern to your body, so accurate measurements matter more than anything else. It takes about 15 minutes with a helper.</p>
            </header>

            <section class="gd-section">
                <h2 class="gd-h2">Before you start</h2>
                <ul class="gd-measure-list">
                    <li><strong>Get a helper</strong><p>Measuring yourself twists the body and skews the numbers. Ask someone to hold the tape for you.</p></li>
                    <li><strong>Use a soft tape</strong><p>A flexible tailor's tape, not a metal one. Keep it flat and level, snug but not tight.</p></li>
                    <li><strong>Wear thin clothing</strong><p>Measure over underwear or a thin base layer. Do not measure over jeans, jackets or padding.</p></li>
                    <li><strong>Stand naturally</strong><p>Feet together, shoulders relaxed, breathing normally. For race suits, also note how you sit on the bike in the instructions.</p></li>
                </ul>
            </section>

            @foreach($groups as $group)
                <section class="gd-section">
                    <h2 class="gd-h2">{{ $group['title'] }}</h2>
                    <ul class="gd-measure-list">
                        @foreach($group['fields'] as $field)
                            <li>
                                <strong>{{ $field['label'] }}@if($field['women'] ?? false)<span class="gd-tag">Women</span>@endif</strong>
                                <p>{{ $field['help'] }}</p>
                                <p class="gd-help" style="margin-top:6px">Needed for: {{ collect($field['for'])->map(fn ($g) => strtolower(\Illuminate\Support\Str::before($garments[$g], ' (')))->implode(', ') }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <section class="gd-section" style="margin-bottom:40px">
                <h2 class="gd-h2">Send your measurements</h2>
                <p class="gd-lede">Once you have your numbers, send them with your order number. The form only asks for the measurements your garment needs. Photos from the front, side and back help us check proportions.</p>
                <div class="gd-btns">
                    <a class="gd-btn" href="{{ route('guide.measurement-form') }}">Open the measurement form</a>
                    <a class="gd-btn gd-btn--ghost" href="{{ route('guide.size-guide') }}">Size guide</a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

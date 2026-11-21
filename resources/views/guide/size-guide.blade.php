@php
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
    $labels = ['Suit' => 'Race suits', 'Jacket' => 'Jackets', 'Pant' => 'Pants', 'Vest' => 'Vests', 'Gloves' => 'Gloves', 'Boots' => 'Boots'];
    // Normalise each chart to [fit => [unit => table]] so the page can switch fit and unit instantly.
    $prepared = [];
    foreach ($charts as $type => $chart) {
        $byFit = [];
        foreach ($chart['tables'] as $table) {
            $fit = in_array($table['gender'] ?? '', ['men', 'women'], true) ? $table['gender'] : 'all';
            $unit = strtoupper((string) ($table['unit'] ?? 'CENTIMETER')) === 'INCHES' ? 'in' : 'cm';
            $byFit[$fit][$unit] = ['columns' => $table['columns'] ?? [], 'rows' => $table['rows'] ?? []];
        }
        $prepared[$type] = ['label' => $labels[$type] ?? $type, 'fits' => $byFit, 'how' => array_values(array_filter(array_map('trim', preg_split('/\R/', $chart['how_to_measure']))))];
    }
@endphp
<x-app-layout :title="'Size Guide — '.$store" :meta-description="'Size charts for '.$store.' race suits, jackets, pants, vests, gloves and boots, in centimetres and inches, for men and women.'" page-key="size-guide">
    <div class="gd" x-data="{ unit: 'cm', fit: {}, chart: @js(array_key_first($prepared)) }">
        <div class="rj-container">
            <nav class="rj-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('guide.how-it-works') }}">How it works</a><span>/</span><span aria-current="page">Size guide</span>
            </nav>

            @include('guide._partials', ['noTabs' => true])
            <header class="gd-head gd-head--center">
                <h1 class="gd-title"><span>Size guide</span></h1>
                <p class="gd-lede">Find your standard size below. Measurements are body measurements, not garment measurements. Between two sizes, or want a closer fit? Choose <strong>Custom Size</strong> and we will cut it to your own measurements.</p>
            </header>

            @if($prepared === [])
                <div class="gd-alert">Size charts are shown on each product page.</div>
            @else
                <section class="gd-section">
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px">
                        <div class="gd-seg" role="tablist" aria-label="Garment">
                            @foreach($prepared as $type => $data)
                                <button type="button" role="tab" :class="{ 'is-active': chart === @js($type) }" :aria-selected="chart === @js($type)" @click="chart = @js($type)">{{ $data['label'] }}</button>
                            @endforeach
                        </div>
                        <div class="gd-seg" role="group" aria-label="Units">
                            <button type="button" :class="{ 'is-active': unit === 'cm' }" @click="unit = 'cm'">Centimeter</button>
                            <button type="button" :class="{ 'is-active': unit === 'in' }" @click="unit = 'in'">Inches</button>
                        </div>
                    </div>

                    @foreach($prepared as $type => $data)
                        @php($fits = array_keys($data['fits']))
                        <div x-show="chart === @js($type)" @if(! $loop->first) x-cloak @endif x-init="fit[@js($type)] = @js($fits[0])">
                            <h2 class="gd-h2">{{ $data['label'] }}</h2>
                            @if(count($fits) > 1)
                                <div class="gd-seg" role="tablist" aria-label="Fit" style="margin-bottom:14px">
                                    @foreach($fits as $fitKey)
                                        <button type="button" role="tab" :class="{ 'is-active': fit[@js($type)] === @js($fitKey) }" @click="fit[@js($type)] = @js($fitKey)">{{ $fitKey === 'women' ? 'Women' : ($fitKey === 'men' ? 'Men' : 'All') }}</button>
                                    @endforeach
                                </div>
                            @endif
                            @foreach($data['fits'] as $fitKey => $units)
                                @foreach($units as $unitKey => $table)
                                    <div class="gd-table-wrap" x-show="fit[@js($type)] === @js($fitKey) && unit === @js($unitKey)" @if(! ($loop->parent->first && $loop->first)) x-cloak @endif>
                                        <table class="gd-table">
                                            <thead><tr>@foreach($table['columns'] as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead>
                                            <tbody>
                                                @foreach($table['rows'] as $row)
                                                    <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach
                            @endforeach

                            @if($data['how'])
                                <h3 class="gd-eyebrow" style="margin:24px 0 10px">How to measure</h3>
                                <ul class="gd-measure-list">
                                    @foreach($data['how'] as $line)
                                        @php([$name, $text] = str_contains($line, ':') ? array_map('trim', explode(':', $line, 2)) : ['', $line])
                                        <li>@if($name !== '')<strong>{{ $name }}</strong>@endif<p>{{ $text }}</p></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="gd-section" style="margin-bottom:40px">
                <h2 class="gd-h2">Not sure about your size?</h2>
                <p class="gd-lede">Custom Size costs a little more and takes {{ $guide['ways']['custom_size']['lead_time'] }} instead of {{ $guide['ways']['standard']['lead_time'] }}, but the piece is cut to your body. Follow the measuring guide, then send your numbers with your order number.</p>
                <div class="gd-btns">
                    <a class="gd-btn" href="{{ route('guide.how-to-measure') }}">How to measure</a>
                    <a class="gd-btn gd-btn--ghost" href="{{ route('guide.measurement-form') }}">Measurement form</a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>

@props(['title', 'heading' => null, 'intro' => '', 'pageKey' => 'legal', 'toc' => [], 'see' => [], 'customKey' => null, 'variant' => 'legal', 'description' => null])
@php
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
    $custom = $customKey ? trim((string) setting($customKey)) : '';
    $updated = 'November 2026';
@endphp
<x-app-layout :title="$title.' — '.$store" :meta-description="$description" :page-key="$pageKey">
    @include('guide._partials', ['noTabs' => true])
    <div class="gd lg lg--{{ $variant }}">
        <div class="rj-container">
            <nav class="rj-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><span>/</span><span aria-current="page">{{ $title }}</span>
            </nav>

            <header class="gd-head gd-head--center">
                <h1 class="gd-title"><span>{{ $heading ?? $title }}</span></h1>
                <p class="gd-lede">{{ $intro }}@if($custom === '' && $variant !== 'policy') Last updated: {{ $updated }}.@endif</p>
            </header>

            @if($custom !== '')
                <div class="lg-body"><p style="white-space:pre-line">{{ $custom }}</p></div>
            @else
                @if(count($toc) && $variant !== 'policy')
                    <nav class="lg-toc" aria-label="On this page">
                        <strong>On this page</strong>
                        <ol>
                            @foreach($toc as $id => $label)
                                <li><a href="#{{ $id }}">{{ $label }}</a></li>
                            @endforeach
                        </ol>
                    </nav>
                @endif
                <div class="lg-body">{{ $slot }}</div>
            @endif

            @if(count($see))
                @if($variant === 'policy')
                    <div class="lg-related">
                        <h2>Related information</h2>
                        <ul>
                            @foreach($see as $label => $url)
                                <li><a href="{{ $url }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="lg-see">See also:
                        @foreach($see as $label => $url)
                            <a class="gd-link" href="{{ $url }}">{{ $label }}</a>@if(! $loop->last) &middot; @endif
                        @endforeach
                    </p>
                @endif
            @endif
        </div>
    </div>
    @once
    <style>
        .lg {margin-bottom:56px;}
        .lg-toc {margin:26px 0 0;max-width:860px;}
        .lg-toc strong {position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);}
        .lg-toc ol {margin:0;padding:0 0 26px;list-style:none;display:flex;flex-wrap:wrap;gap:10px;}
        .lg-toc a {display:block;padding:12px 16px;border:1px solid var(--gd-line);background:#fff;font-size:14px;color:var(--gd-ink);text-decoration:none;line-height:1.2;}
        .lg-toc a:hover {border-color:var(--gd-ink);}
        .lg-body {max-width:860px;}
        .lg-body section {padding:22px 0 26px;border-top:3px solid var(--gd-ink);scroll-margin-top:90px;}
                .lg-body h2 {margin:0 0 14px;font-size:18px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;}
        .lg-body p {margin:0 0 12px;line-height:1.7;color:#3a3532;font-size:15px;}
        .lg-body ul {list-style:decimal;margin:0 0 12px;padding-left:22px;line-height:1.7;color:#3a3532;font-size:15px;}
        .lg-body li {margin-bottom:8px;padding-left:4px;}
        .lg-body li::marker {color:var(--gd-muted);font-size:13px;}
        .lg-body a {color:var(--gd-ink);font-weight:700;text-decoration:underline;text-underline-offset:3px;}
        .lg-body address {font-style:normal;line-height:1.7;padding:14px 18px;background:var(--gd-soft);margin:0 0 12px;display:inline-block;min-width:260px;}
        .lg-body .lg-table-wrap {overflow-x:auto;border:1px solid var(--gd-line);margin:0 0 14px;}
        .lg-body table {width:100%;border-collapse:collapse;font-size:14px;min-width:480px;}
        .lg-body th,.lg-body td {padding:12px 14px;border-bottom:1px solid var(--gd-line);text-align:left;vertical-align:top;line-height:1.55;}
        .lg-body tr:last-child td {border-bottom:0;}
        .lg-body th {background:var(--gd-soft);font-size:11px;letter-spacing:.08em;text-transform:uppercase;}
        .lg-body td:first-child {font-weight:700;white-space:nowrap;}
        .lg-body td small {display:block;font-weight:400;color:var(--gd-muted);}
        .lg-body .lg-note {background:var(--gd-soft);padding:16px 20px;}
        .lg-see {margin:10px 0 0;padding-top:22px;border-top:3px solid var(--gd-ink);font-size:14px;max-width:860px;}
        /* Policy variant: large plain headings, round bullets, bold figures (shipping and returns) */
        .lg--policy .lg-body {max-width:860px;}
        .lg--policy .lg-body section {border-top:0;padding:14px 0 10px;}
        .lg--policy .lg-body h2,.lg-related h2 {font-size:26px;font-weight:800;letter-spacing:.02em;margin:0 0 12px;}
        .lg--policy .lg-body ul,.lg-related ul {list-style:disc;padding-left:20px;}
        .lg--policy .lg-body li::marker,.lg-related li::marker {color:var(--gd-muted);font-size:inherit;}
        .lg--policy .lg-body th {font-size:14px;letter-spacing:0;text-transform:none;font-weight:600;}
        .lg--policy .lg-body td:first-child {white-space:normal;}
        .lg--policy .lg-body .lg-table-wrap {border:0;}
        .lg--policy .lg-body th,.lg--policy .lg-body td {padding:14px 16px;}
        .lg-related {max-width:860px;padding:14px 0 10px;}
        .lg-related ul {margin:0;line-height:1.8;font-size:15px;}
        .lg-related a {color:var(--gd-ink);font-weight:700;text-decoration:underline;text-underline-offset:3px;}
        .lg .gd-title span {min-width:0;}
    </style>
    @endonce
</x-app-layout>

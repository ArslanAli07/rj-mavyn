{{-- Shared guide styling and sub-navigation. Uses RJ theme tokens when present, with neutral fallbacks. --}}
@once
<style>
    .gd {--gd-ink:#17120f;--gd-muted:var(--rj-muted,#6f6f6f);--gd-line:var(--rj-line,#d8d8d8);--gd-soft:#f6f5f2;--gd-accent:var(--rj-accent,#17120f);color:var(--gd-ink);}
    .gd .rj-container {width:100%;max-width:var(--rj-measure,1240px);margin-inline:auto;padding-inline:var(--rj-gutter,20px);}
    .gd-head {padding:28px 0 8px;}
    .gd-eyebrow {display:block;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gd-muted);}
    .gd-title {margin:10px 0 12px;font-size:30px;font-weight:800;letter-spacing:.04em;line-height:1.08;text-transform:uppercase;}
    @media(min-width:768px){.gd-title {font-size:40px;}}
    .gd-head--center {padding:34px 0 0;}
    .gd-head--center .gd-title {display:flex;align-items:center;gap:24px;margin:0 0 30px;text-transform:uppercase;}
    .gd-head--center .gd-title::before,.gd-head--center .gd-title::after {content:"";flex:1;height:1px;background:var(--gd-line);}
    .gd-head--center .gd-lede {max-width:none;}
    .gd-lede {max-width:720px;font-size:16px;line-height:1.65;color:#3a3532;margin:0;}
    .gd-tabs {display:flex;gap:6px;flex-wrap:wrap;margin:22px 0 6px;padding:0;list-style:none;}
    .gd-tabs a {display:inline-block;padding:9px 16px;border:1px solid var(--gd-line);border-radius:100px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--gd-ink);text-decoration:none;}
    .gd-tabs a[aria-current="page"] {background:var(--gd-ink);border-color:var(--gd-ink);color:#fff;}
    .gd-section {padding:34px 0;border-top:1px solid var(--gd-line);margin-top:30px;}
    .gd-h2 {font-size:20px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;margin:0 0 18px;}
    .gd-grid3 {display:grid;gap:16px;grid-template-columns:1fr;}
    @media(min-width:820px){.gd-grid3 {grid-template-columns:repeat(3,1fr);}}
    .gd-card {border:1px solid var(--gd-line);padding:22px;background:#fff;display:flex;flex-direction:column;gap:10px;}
    .gd-card h3 {margin:0;font-size:15px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;}
    .gd-card p {margin:0;color:#3a3532;line-height:1.6;font-size:14px;}
    .gd-pill {align-self:flex-start;margin-top:auto;padding:6px 12px;background:var(--gd-soft);font-size:12px;font-weight:700;letter-spacing:.04em;}
    .gd-steps {list-style:none;margin:0;padding:0;display:grid;gap:0;counter-reset:gd;}
    .gd-step {display:grid;grid-template-columns:44px 1fr;gap:16px;padding:20px 0;border-bottom:1px solid var(--gd-line);}
    .gd-step:last-child {border-bottom:0;}
    .gd-step__n {width:36px;height:36px;border-radius:50%;background:var(--gd-ink);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;}
    .gd-step h3 {margin:6px 0 8px;font-size:15px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;}
    .gd-step p {margin:0;line-height:1.65;color:#3a3532;max-width:760px;}
    .gd a.gd-link {color:var(--gd-ink);font-weight:700;text-decoration:underline;text-underline-offset:3px;}
    .gd-btns {display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;}
    .gd-btn {display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 22px;border-radius:100px;border:1px solid var(--gd-ink);background:var(--gd-ink);color:#fff;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;text-decoration:none;cursor:pointer;}
    .gd-btn--ghost {background:#fff;color:var(--gd-ink);}
    .gd-note {background:var(--gd-soft);padding:18px 20px;line-height:1.6;font-size:14px;}
    .gd-table-wrap {overflow-x:auto;border:1px solid var(--gd-line);}
    .gd-table {border-collapse:collapse;width:100%;min-width:560px;font-size:13px;}
    .gd-table th,.gd-table td {padding:9px 12px;border-bottom:1px solid var(--gd-line);text-align:left;white-space:nowrap;}
    .gd-table th {background:var(--gd-soft);font-size:11px;letter-spacing:.08em;text-transform:uppercase;}
    .gd-seg {display:inline-flex;border:1px solid var(--gd-line);border-radius:100px;overflow:hidden;}
    .gd-seg button,.gd-seg label {border:0;background:#fff;padding:8px 16px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;color:var(--gd-ink);}
    .gd-seg .is-active,.gd-seg input:checked + span {background:var(--gd-ink);color:#fff;}
    .gd-seg input {position:absolute;opacity:0;pointer-events:none;}
    .gd-seg label span {display:inline-block;padding:8px 16px;margin:-8px -16px;}
    .gd-field {display:flex;flex-direction:column;gap:6px;}
    .gd-field label {font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;}
    .gd-input {height:44px;padding:0 12px;border:1px solid var(--gd-line);background:#fff;font:inherit;font-size:14px;color:var(--gd-ink);width:100%;box-sizing:border-box;}
    textarea.gd-input {height:auto;min-height:100px;padding:10px 12px;}
    .gd-form-grid {display:grid;gap:16px;grid-template-columns:1fr;}
    @media(min-width:700px){.gd-form-grid {grid-template-columns:repeat(2,1fr);}.gd-form-grid--3 {grid-template-columns:repeat(3,1fr);}}
    .gd-help {font-size:12px;color:var(--gd-muted);line-height:1.5;}
    .gd-error {color:#b42318;font-size:12px;}
    .gd-alert {padding:14px 18px;border:1px solid var(--gd-line);background:var(--gd-soft);margin:18px 0;line-height:1.6;}
    .gd-alert--error {border-color:#f1b7b0;background:#fdf3f2;color:#7a1a12;}
    .gd-progress {display:flex;gap:8px;list-style:none;padding:0;margin:20px 0;}
    .gd-progress li {flex:1;padding:10px 12px;border-bottom:3px solid var(--gd-line);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--gd-muted);}
    .gd-progress li.is-active {border-color:var(--gd-ink);color:var(--gd-ink);}
    .gd-measure-list {display:grid;gap:12px;grid-template-columns:1fr;list-style:none;padding:0;margin:0;}
    @media(min-width:820px){.gd-measure-list {grid-template-columns:repeat(2,1fr);}}
    .gd-measure-list li {border:1px solid var(--gd-line);padding:16px 18px;background:#fff;}
    .gd-measure-list strong {display:block;font-size:13px;letter-spacing:.04em;text-transform:uppercase;margin-bottom:6px;}
    .gd-measure-list p {margin:0;font-size:14px;line-height:1.55;color:#3a3532;}
    .gd-tag {display:inline-block;margin-left:6px;padding:2px 8px;background:var(--gd-soft);font-size:10px;letter-spacing:.08em;text-transform:uppercase;vertical-align:middle;}
    [x-cloak] {display:none !important;}
</style>
@endonce

@php
    $guideTabs = [
        ['route' => 'guide.how-it-works', 'label' => 'How it works'],
        ['route' => 'guide.size-guide', 'label' => 'Size guide'],
        ['route' => 'guide.how-to-measure', 'label' => 'How to measure'],
        ['route' => 'guide.measurement-form', 'label' => 'Measurement form'],
    ];
@endphp
@unless($noTabs ?? false)
<nav aria-label="Ordering guide">
    <ul class="gd-tabs">
        @foreach($guideTabs as $tab)
            <li><a href="{{ route($tab['route']) }}" @if(request()->routeIs($tab['route'])) aria-current="page" @endif>{{ $tab['label'] }}</a></li>
        @endforeach
    </ul>
</nav>
@endunless

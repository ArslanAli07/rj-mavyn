@php
    $storeName = setting('site_name', config('site.name', 'RJ Motorbike'));
    $addr = trim((string) setting('site_address'));
    $mail = trim((string) setting('site_email', config('site.email')));
    $tel = trim((string) setting('site_phone'));
@endphp
<address>
    <strong>{{ $storeName }}</strong>
    @if($addr)<br>{!! nl2br(e($addr)) !!}@endif
    @if($mail)<br>Email: <a href="mailto:{{ $mail }}">{{ $mail }}</a>@endif
    @if($tel)<br>Phone: {{ $tel }}@endif
</address>

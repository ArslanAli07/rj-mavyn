{{-- Measurement rows grouped by body area. Expects $submission. --}}
@php($grouped = collect($submission->rows())->groupBy('group'))
<div class="d-grid admin-cols-1 admin-cols-lg-2 gap-4">
    @foreach($grouped as $group => $rows)
        <div>
            <h4 class="small fw-semibold text-body-secondary text-uppercase mb-2">{{ $group }}</h4>
            <table class="table table-sm admin-table small mb-0">
                <tbody>
                    @foreach($rows as $row)
                        <tr><td class="text-body-secondary">{{ $row['label'] }}</td><td class="text-end fw-semibold">{{ $row['value'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@if($submission->notes)
    <div class="mt-3"><span class="small fw-semibold text-body-secondary d-block">Customer instructions</span><p class="small mb-0" style="white-space:pre-line">{{ $submission->notes }}</p></div>
@endif
@if($submission->photos)
    <div class="mt-3 d-flex flex-wrap gap-2">
        @if(\App\Services\AdminPermissions::canRoute(auth('admin')->user(), 'admin.measurements.photo'))
            @foreach($submission->photos as $i => $photo)
                <a href="{{ route('admin.measurements.photo', [$submission, $i]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.measurements.photo', [$submission, $i]) }}" alt="Customer photo {{ $i + 1 }}" style="width:110px;height:140px;object-fit:cover" class="rounded-2 border"></a>
            @endforeach
        @else
            <span class="small text-body-secondary">{{ count($submission->photos) }} photo(s) attached. Viewing them needs the receipts permission.</span>
        @endif
    </div>
@endif

@if($stockMovements->isNotEmpty())
<div class="card bg-white border border-light-subtle rounded-2 p-4 admin-stack-3">
    <h3 class="text-body admin-section-title">Recent stock movements</h3>
    <p class="small text-body-secondary">Latest 20 changes. On-hand stock includes reserved units.</p>
    <div class="table-responsive">
        <table class="table admin-table align-middle">
            <thead><tr><th class="p-2">Date / staff</th><th class="p-2">Stock / action</th><th class="p-2">On-hand change</th><th class="p-2">Reserved change</th><th class="p-2">On hand / reserved after</th></tr></thead>
            <tbody>
                @foreach($stockMovements as $movement)
                    <tr class="border-top border-light-subtle">
                        <td class="p-2">{{ store_time($movement->created_at)->format('M j, H:i') }}<br>{{ $movement->admin?->name ?? 'Checkout / order workflow' }}</td>
                        <td class="p-2">{{ $movement->context['variant_name'] ?? 'Shared product stock' }}<br>{{ ucfirst($movement->kind) }}</td>
                        <td class="p-2">{{ $movement->stock_delta > 0 ? '+' : '' }}{{ $movement->stock_delta }}</td>
                        <td class="p-2">{{ $movement->reserved_delta > 0 ? '+' : '' }}{{ $movement->reserved_delta }}</td>
                        <td class="p-2">{{ $movement->stock_after }} / {{ $movement->reserved_after }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

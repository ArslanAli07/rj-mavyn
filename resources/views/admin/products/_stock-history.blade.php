@if($stockMovements->isNotEmpty())
<div class="bg-white border border-cream-150 rounded-2xl p-6 space-y-3">
    <h3 class="font-heading font-bold text-sm text-espresso-800">Recent stock movements</h3>
    <p class="text-xs text-espresso-500">Latest 20 changes. On-hand stock includes reserved units.</p>
    <div class="overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead><tr><th class="p-2">Date / staff</th><th class="p-2">Stock / action</th><th class="p-2">On-hand change</th><th class="p-2">Reserved change</th><th class="p-2">On hand / reserved after</th></tr></thead>
            <tbody>
                @foreach($stockMovements as $movement)
                    <tr class="border-t border-cream-100">
                        <td class="p-2">{{ $movement->created_at->format('M j, H:i') }}<br>{{ $movement->admin?->name ?? 'Checkout / order workflow' }}</td>
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

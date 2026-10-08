<div x-data="{ inventoryMode: @js(old('inventory_mode', $product->inventory_mode ?? 'untracked')) }" class="space-y-2">
    <label for="inventory_mode" class="label-luxury">Inventory</label>
    <select id="inventory_mode" name="inventory_mode" x-model="inventoryMode" @change="$dispatch('inventory-mode-changed', { mode: inventoryMode })" class="input-luxury text-sm">
        <option value="untracked">Untracked / made to order</option>
        <option value="shared">Shared stock across variants</option>
        <option value="variant">Separate stock per variant</option>
    </select>
    @error('inventory_mode') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
    <input type="hidden" name="inventory_version" value="{{ old('inventory_version', $product->inventory_version ?? 0) }}">
    <input type="hidden" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" :disabled="inventoryMode === 'shared'">
    <div x-show="inventoryMode === 'shared'" x-cloak>
        <label for="stock_quantity" class="label-luxury">Units on hand</label>
        <input type="number" id="stock_quantity" name="stock_quantity" min="0" max="2147483647" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" :disabled="inventoryMode !== 'shared'" :required="inventoryMode === 'shared'" class="input-luxury text-sm">
        <p class="text-xs text-espresso-500">{{ $product->reserved_quantity ?? 0 }} reserved. On-hand stock includes these units.</p>
    </div>
    <p x-show="inventoryMode === 'untracked'" class="text-xs text-espresso-500">Use In Stock to control availability. Quantities are not deducted.</p>
    <p x-show="inventoryMode === 'variant'" class="text-xs text-espresso-500">Set tracking and on-hand units in each variant below.</p>
    @error('stock_quantity') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
</div>

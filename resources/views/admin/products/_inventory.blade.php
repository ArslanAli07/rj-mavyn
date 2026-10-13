<div x-data="{ inventoryMode: @js(old('inventory_mode', $product->inventory_mode ?? 'untracked')) }" class="admin-stack-2">
    <label for="inventory_mode" class="form-label">Inventory</label>
    <select id="inventory_mode" name="inventory_mode" x-model="inventoryMode" @change="$dispatch('inventory-mode-changed', { mode: inventoryMode })" class="form-select small">
        <option value="untracked">Untracked / made to order</option>
        <option value="shared">Shared stock across variants</option>
        <option value="variant">Separate stock per variant</option>
    </select>
    @error('inventory_mode') <p class="text-danger small">{{ $message }}</p> @enderror
    <input type="hidden" name="inventory_version" value="{{ old('inventory_version', $product->inventory_version ?? 0) }}">
    <input type="hidden" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" :disabled="inventoryMode === 'shared'">
    <div x-show.important="inventoryMode === 'shared'" x-cloak>
        <label for="stock_quantity" class="form-label">Units on hand</label>
        <input type="number" id="stock_quantity" name="stock_quantity" min="0" max="2147483647" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" :disabled="inventoryMode !== 'shared'" :required="inventoryMode === 'shared'" class="form-control small num">
        <p class="small text-body-secondary">{{ $product->reserved_quantity ?? 0 }} reserved. On-hand stock includes these units.</p>
    </div>
    <p x-show.important="inventoryMode === 'untracked'" class="small text-body-secondary">Use In Stock to control availability. Quantities are not deducted.</p>
    <p x-show.important="inventoryMode === 'variant'" class="small text-body-secondary">Set tracking and on-hand units in each variant below.</p>
    @error('stock_quantity') <p class="text-danger small">{{ $message }}</p> @enderror
</div>

{{-- Shared admin product variants + option groups editor (expects Alpine productOptionsManager) --}}
<div class="bg-white border border-cream-150 rounded-2xl p-6 shadow-sm space-y-4" x-data="productOptionsManager" @inventory-mode-changed.window="inventoryMode = $event.detail.mode">
    <div class="flex items-center justify-between border-b border-cream-100 pb-3">
        <div>
            <h3 class="font-heading font-bold text-sm text-espresso-800">Base Variants *</h3>
            <p class="text-[11px] text-espresso-400 mt-0.5">Sizes or base choices with their own prices. Mark one as default for catalog display and quick-add.</p>
        </div>
        <button type="button" @click="addVariant()" class="px-3 py-1.5 bg-primary-50 hover:bg-primary-100 text-primary-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1 border border-primary-150">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add Variant
        </button>
    </div>

    <div class="space-y-4">
        <template x-for="(variant, index) in variants" :key="'variant-' + index">
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end bg-cream-50/40 p-4 rounded-xl border border-cream-100 relative">
                <input type="hidden" :name="'variants[' + index + '][id]'" :value="variant.id || ''">
                <input type="hidden" :name="'variants[' + index + '][sort_order]'" :value="index">
                <input type="hidden" :name="'variants[' + index + '][inventory_version]'" :value="variant.inventory_version">

                <div class="sm:col-span-12 flex flex-wrap items-center gap-3 text-xs">
                    <label><input type="checkbox" x-model="variant.in_stock"> Available</label>
                    <input type="hidden" :name="'variants[' + index + '][in_stock]'" :value="variant.in_stock ? 1 : 0">
                    <div x-show="inventoryMode === 'variant'" class="flex flex-wrap items-center gap-3" x-cloak>
                        <label><input type="checkbox" x-model="variant.track_inventory"> Track stock</label>
                        <input type="hidden" :name="'variants[' + index + '][track_inventory]'" :value="variant.track_inventory ? 1 : 0" :disabled="inventoryMode !== 'variant'">
                        <label x-show="variant.track_inventory">Units on hand
                            <input type="number" min="0" max="2147483647" :name="'variants[' + index + '][stock_quantity]'" x-model="variant.stock_quantity" :disabled="inventoryMode !== 'variant' || !variant.track_inventory" class="input-luxury text-xs w-24">
                        </label>
                        <span x-show="variant.track_inventory"><span x-text="variant.reserved_quantity"></span> reserved</span>
                        <span x-show="!variant.track_inventory">Untracked / made to order</span>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="label-luxury text-[11px]">Name *</label>
                    <input type="text" :name="'variants[' + index + '][name]'" x-model="variant.name" required class="input-luxury text-xs py-1.5" placeholder="e.g. Large">
                    <template x-if="errors['variants.' + index + '.name']">
                        <p class="text-red-500 text-[10px] mt-1" x-text="errors['variants.' + index + '.name'][0]"></p>
                    </template>
                </div>

                <div class="sm:col-span-2">
                    <label class="label-luxury text-[11px]">Regular Price *</label>
                    <input type="number" step="0.01" :name="'variants[' + index + '][price]'" x-model="variant.price" required class="input-luxury text-xs py-1.5" placeholder="Price">
                </div>

                <div class="sm:col-span-2">
                    <label class="label-luxury text-[11px]">Discount Price</label>
                    <input type="number" step="0.01" :name="'variants[' + index + '][discount_price]'" x-model="variant.discount_price" class="input-luxury text-xs py-1.5" placeholder="Optional">
                </div>

                <div class="sm:col-span-2">
                    <label class="label-luxury text-[11px]">Servings</label>
                    <input type="text" :name="'variants[' + index + '][servings]'" x-model="variant.servings" class="input-luxury text-xs py-1.5" placeholder="Optional">
                </div>

                <div class="sm:col-span-2 flex flex-col gap-2 pb-1">
                    <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-espresso-700 cursor-pointer">
                        <input type="radio" name="default_variant_radio" :checked="variant.is_default" @change="setDefaultVariant(index)" class="text-primary-500 focus:ring-primary-300">
                        Default
                    </label>
                    <input type="hidden" :name="'variants[' + index + '][is_default]'" :value="variant.is_default ? '1' : '0'">
                    <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-espresso-700 cursor-pointer">
                        <input type="checkbox" x-model="variant.is_active" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                        Active
                    </label>
                    <input type="hidden" :name="'variants[' + index + '][is_active]'" :value="variant.is_active ? '1' : '0'">
                </div>

                <div class="sm:col-span-1 flex justify-end">
                    <button type="button" @click="removeVariant(index)" class="p-2 bg-red-50 hover:bg-red-100 text-red-650 rounded-xl border border-red-150 transition-colors" title="Delete Variant">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </template>
        @error('variants') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
    </div>

    <div class="border-t border-cream-100 pt-5 mt-2">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-heading font-bold text-sm text-espresso-800">Extras & Toppings</h3>
                <p class="text-[11px] text-espresso-400 mt-0.5">Optional groups like Ice or Toppings. Customers can pick single or multiple choices with price adjustments.</p>
            </div>
            <button type="button" @click="addOptionGroup()" class="px-3 py-1.5 bg-cream-100 hover:bg-cream-200 text-espresso-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1 border border-cream-200">
                Add Option Group
            </button>
        </div>

        <div class="space-y-4">
            <template x-for="(group, gIndex) in optionGroups" :key="'group-' + gIndex">
                <div class="border border-cream-200 rounded-2xl p-4 bg-white space-y-3">
                    <input type="hidden" :name="'option_groups[' + gIndex + '][id]'" :value="group.id || ''">
                    <input type="hidden" :name="'option_groups[' + gIndex + '][sort_order]'" :value="gIndex">

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div class="sm:col-span-3">
                            <label class="label-luxury text-[11px]">Group Name *</label>
                            <input type="text" :name="'option_groups[' + gIndex + '][name]'" x-model="group.name" required class="input-luxury text-xs py-1.5" placeholder="e.g. Toppings">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label-luxury text-[11px]">Selection</label>
                            <select :name="'option_groups[' + gIndex + '][selection_type]'" x-model="group.selection_type" class="input-luxury text-xs py-1.5" @change="onSelectionTypeChange(gIndex)">
                                <option value="single">Single</option>
                                <option value="multiple">Multiple</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label-luxury text-[11px]">Min</label>
                            <input type="number" min="0" :name="'option_groups[' + gIndex + '][min_selections]'" x-model="group.min_selections" class="input-luxury text-xs py-1.5">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label-luxury text-[11px]">Max</label>
                            <input type="number" min="1" :name="'option_groups[' + gIndex + '][max_selections]'" x-model="group.max_selections" class="input-luxury text-xs py-1.5" :disabled="group.selection_type === 'single'" :placeholder="group.selection_type === 'single' ? '1' : 'Optional'">
                        </div>
                        <div class="sm:col-span-2 flex flex-col gap-2 pb-1">
                            <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-espresso-700 cursor-pointer">
                                <input type="checkbox" x-model="group.is_required" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                                Required
                            </label>
                            <input type="hidden" :name="'option_groups[' + gIndex + '][is_required]'" :value="group.is_required ? '1' : '0'">
                            <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-espresso-700 cursor-pointer" title="Must be on for toppings to show on the product page">
                                <input type="checkbox" x-model="group.is_active" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                                Visible
                            </label>
                            <input type="hidden" :name="'option_groups[' + gIndex + '][is_active]'" :value="group.is_active ? '1' : '0'">
                        </div>
                        <div class="sm:col-span-1 flex justify-end">
                            <button type="button" @click="removeOptionGroup(gIndex)" class="p-2 bg-red-50 hover:bg-red-100 text-red-650 rounded-xl border border-red-150" title="Remove group">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2 pl-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-espresso-500">Choices</span>
                            <button type="button" @click="addOptionValue(gIndex)" class="text-[11px] font-bold text-primary-600 hover:text-primary-700">+ Add choice</button>
                        </div>
                        <template x-for="(value, vIndex) in group.values" :key="'value-' + gIndex + '-' + vIndex">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center bg-cream-50/60 rounded-xl p-2 border border-cream-100">
                                <input type="hidden" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][id]'" :value="value.id || ''">
                                <input type="hidden" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][sort_order]'" :value="vIndex">
                                <div class="sm:col-span-5">
                                    <input type="text" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][name]'" x-model="value.name" required class="input-luxury text-xs py-1.5" placeholder="e.g. Extra berries">
                                </div>
                                <div class="sm:col-span-3">
                                    <input type="number" step="0.01" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][price_adjustment]'" x-model="value.price_adjustment" class="input-luxury text-xs py-1.5" placeholder="Price +/-">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-espresso-700 cursor-pointer">
                                        <input type="checkbox" x-model="value.is_active" class="rounded border-cream-300 text-primary-500 focus:ring-primary-300">
                                        Visible
                                    </label>
                                    <input type="hidden" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][is_active]'" :value="value.is_active ? '1' : '0'">
                                </div>
                                <div class="sm:col-span-1 flex justify-end">
                                    <button type="button" @click="removeOptionValue(gIndex, vIndex)" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Remove choice">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

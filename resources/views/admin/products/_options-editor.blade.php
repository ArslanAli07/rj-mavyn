{{-- Shared admin product variants + option groups editor (expects Alpine productOptionsManager) --}}
<div class="card bg-white border border-light-subtle rounded-2 p-4 shadow-sm admin-stack-4" x-data="productOptionsManager" @inventory-mode-changed.window="inventoryMode = $event.detail.mode">
    <div class="d-flex align-items-center justify-content-between border-bottom border-light-subtle pb-3">
        <div>
            <h3 class="text-body admin-section-title">Base variants *</h3>
            <p class="small text-body-secondary mt-1">Variants or base choices with their own prices. Mark one as default for catalog display and quick-add.</p>
        </div>
        <button type="button" @click="addVariant()" class="btn btn-sm btn-outline-secondary px-3 py-2 bg-primary-subtle text-primary rounded-2 small fw-semibold d-flex align-items-center gap-1 border border-primary-subtle">
            <svg class="admin-width-3-5 admin-height-3-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Add Variant
        </button>
    </div>

    <div class="admin-stack-4">
        <template x-for="(variant, index) in variants" :key="'variant-' + index">
            <div class="d-grid admin-cols-1 admin-cols-sm-12 gap-3 align-items-end bg-body-tertiary p-3 rounded-2 border border-light-subtle position-relative">
                <input type="hidden" :name="'variants[' + index + '][id]'" :value="variant.id || ''">
                <input type="hidden" :name="'variants[' + index + '][sort_order]'" :value="index">
                <input type="hidden" :name="'variants[' + index + '][inventory_version]'" :value="variant.inventory_version">

                <div class="admin-span-sm-12 d-flex flex-wrap align-items-center gap-3">
                    <label><input type="checkbox" x-model="variant.in_stock" class="form-check-input"> Available</label>
                    <input type="hidden" :name="'variants[' + index + '][in_stock]'" :value="variant.in_stock ? 1 : 0">
                    <div x-show.important="inventoryMode === 'variant'" class="d-flex flex-wrap align-items-center gap-3" x-cloak>
                        <label><input type="checkbox" x-model="variant.track_inventory" class="form-check-input"> Track stock</label>
                        <input type="hidden" :name="'variants[' + index + '][track_inventory]'" :value="variant.track_inventory ? 1 : 0" :disabled="inventoryMode !== 'variant'">
                        <label x-show.important="variant.track_inventory">Units on hand
                            <input type="number" min="0" max="2147483647" :name="'variants[' + index + '][stock_quantity]'" x-model="variant.stock_quantity" :disabled="inventoryMode !== 'variant' || !variant.track_inventory" class="form-control small admin-width-24 num">
                        </label>
                        <span x-show.important="variant.track_inventory"><span x-text="variant.reserved_quantity"></span> reserved</span>
                        <span x-show.important="!variant.track_inventory">Untracked / made to order</span>
                    </div>
                </div>

                <div class="admin-span-sm-3">
                    <label class="form-label small">Name *</label>
                    <input type="text" :name="'variants[' + index + '][name]'" x-model="variant.name" required class="form-control small py-2" placeholder="e.g. Large">
                    <template x-if="errors['variants.' + index + '.name']">
                        <p class="text-danger small mt-1" x-text="errors['variants.' + index + '.name'][0]"></p>
                    </template>
                </div>

                <div class="admin-span-sm-2">
                    <label class="form-label small">Regular price *</label>
                    <input type="number" step="0.01" :name="'variants[' + index + '][price]'" x-model="variant.price" required class="form-control small py-2 num" placeholder="Price">
                </div>

                <div class="admin-span-sm-2">
                    <label class="form-label small">Discount price</label>
                    <input type="number" step="0.01" :name="'variants[' + index + '][discount_price]'" x-model="variant.discount_price" class="form-control small py-2 num" placeholder="Optional">
                </div>

                @foreach(app(\App\Services\ProductAttributesService::class)->fields('variant') as $entry)
                    @php
                        $field = $entry['field'];
                        $attributeName = $entry['legacy'] ? $field->key : 'plugin_attributes]['.$entry['owner'].']['.$field->key;
                        $attributeModel = $entry['legacy'] ? 'variant.'.$field->key : "variant.plugin_attributes['".$entry['owner']."']['".$field->key."']";
                    @endphp
                    <div class="admin-span-sm-2">
                        <label class="form-label small">{{ $field->label }}</label>
                        <input type="text" :name="'variants[' + index + '][{{ $attributeName }}]'" x-model="{{ $attributeModel }}" maxlength="{{ $field->maxLength }}" class="form-control small py-2" placeholder="Optional">
                    </div>
                @endforeach

                <div class="admin-span-sm-2 d-flex flex-column gap-2 pb-1">
                    <label class="d-inline-flex align-items-center gap-2 small fw-semibold text-body admin-clickable">
                        <input type="radio" name="default_variant_radio" :checked="variant.is_default" @change="setDefaultVariant(index)" class="form-check-input">
                        Default
                    </label>
                    <input type="hidden" :name="'variants[' + index + '][is_default]'" :value="variant.is_default ? '1' : '0'">
                    <label class="d-inline-flex align-items-center gap-2 small fw-semibold text-body admin-clickable">
                        <input type="checkbox" x-model="variant.is_active" class="form-check-input">
                        Active
                    </label>
                    <input type="hidden" :name="'variants[' + index + '][is_active]'" :value="variant.is_active ? '1' : '0'">
                </div>

                <div class="admin-span-sm-1 d-flex justify-content-end">
                    <button type="button" @click="removeVariant(index)" class="btn btn-sm btn-outline-danger p-2 bg-danger-subtle text-danger rounded-2 border border-danger-subtle" title="Delete Variant">
                        <svg class="admin-width-4 admin-height-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </template>
        @error('variants') <p class="text-danger small">{{ $message }}</p> @enderror
    </div>

    <div class="border-top border-light-subtle pt-4 mt-2">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="text-body admin-section-title">Extras & toppings</h3>
                <p class="small text-body-secondary mt-1">Optional groups like Ice or Toppings. Customers can pick single or multiple choices with price adjustments.</p>
            </div>
            <button type="button" @click="addOptionGroup()" class="btn btn-sm btn-outline-secondary px-3 py-2 bg-body-tertiary text-body rounded-2 small fw-semibold d-flex align-items-center gap-1 border border-light-subtle">
                Add option group
            </button>
        </div>

        <div class="admin-stack-4">
            <template x-for="(group, gIndex) in optionGroups" :key="'group-' + gIndex">
                <div class="card border border-light-subtle rounded-2 p-3 bg-white admin-stack-3">
                    <input type="hidden" :name="'option_groups[' + gIndex + '][id]'" :value="group.id || ''">
                    <input type="hidden" :name="'option_groups[' + gIndex + '][sort_order]'" :value="gIndex">

                    <div class="d-grid admin-cols-1 admin-cols-sm-12 gap-3 align-items-end">
                        <div class="admin-span-sm-3">
                            <label class="form-label small">Group name *</label>
                            <input type="text" :name="'option_groups[' + gIndex + '][name]'" x-model="group.name" required class="form-control small py-2" placeholder="e.g. Toppings">
                        </div>
                        <div class="admin-span-sm-2">
                            <label class="form-label small">Selection</label>
                            <select :name="'option_groups[' + gIndex + '][selection_type]'" x-model="group.selection_type" class="form-select small py-2" @change="onSelectionTypeChange(gIndex)">
                                <option value="single">Single</option>
                                <option value="multiple">Multiple</option>
                            </select>
                        </div>
                        <div class="admin-span-sm-2">
                            <label class="form-label small">Min</label>
                            <input type="number" min="0" :name="'option_groups[' + gIndex + '][min_selections]'" x-model="group.min_selections" class="form-control small py-2 num">
                        </div>
                        <div class="admin-span-sm-2">
                            <label class="form-label small">Max</label>
                            <input type="number" min="1" :name="'option_groups[' + gIndex + '][max_selections]'" x-model="group.max_selections" class="form-control small py-2 num" :disabled="group.selection_type === 'single'" :placeholder="group.selection_type === 'single' ? '1' : 'Optional'">
                        </div>
                        <div class="admin-span-sm-2 d-flex flex-column gap-2 pb-1">
                            <label class="d-inline-flex align-items-center gap-2 small fw-semibold text-body admin-clickable">
                                <input type="checkbox" x-model="group.is_required" class="form-check-input">
                                Required
                            </label>
                            <input type="hidden" :name="'option_groups[' + gIndex + '][is_required]'" :value="group.is_required ? '1' : '0'">
                            <label class="d-inline-flex align-items-center gap-2 small fw-semibold text-body admin-clickable" title="Must be on for toppings to show on the product page">
                                <input type="checkbox" x-model="group.is_active" class="form-check-input">
                                Visible
                            </label>
                            <input type="hidden" :name="'option_groups[' + gIndex + '][is_active]'" :value="group.is_active ? '1' : '0'">
                        </div>
                        <div class="admin-span-sm-1 d-flex justify-content-end">
                            <button type="button" @click="removeOptionGroup(gIndex)" class="btn btn-sm btn-outline-danger p-2 bg-danger-subtle text-danger rounded-2 border border-danger-subtle" title="Remove group">
                                <svg class="admin-width-4 admin-height-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="admin-stack-2 ps-1">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="small fw-semibold admin-letter-spacing text-body-secondary">Choices</span>
                            <button type="button" @click="addOptionValue(gIndex)" class="btn btn-sm btn-outline-secondary small fw-semibold text-primary">+ Add choice</button>
                        </div>
                        <template x-for="(value, vIndex) in group.values" :key="'value-' + gIndex + '-' + vIndex">
                            <div class="d-grid admin-cols-1 admin-cols-sm-12 gap-2 align-items-center bg-body-tertiary rounded-2 p-2 border border-light-subtle">
                                <input type="hidden" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][id]'" :value="value.id || ''">
                                <input type="hidden" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][sort_order]'" :value="vIndex">
                                <div class="admin-span-sm-5">
                                    <input type="text" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][name]'" x-model="value.name" required class="form-control small py-2" placeholder="e.g. Extra berries">
                                </div>
                                <div class="admin-span-sm-3">
                                    <input type="number" step="0.01" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][price_adjustment]'" x-model="value.price_adjustment" class="form-control small py-2 num" placeholder="Price +/-">
                                </div>
                                <div class="admin-span-sm-3">
                                    <label class="d-inline-flex align-items-center gap-2 small fw-semibold text-body admin-clickable">
                                        <input type="checkbox" x-model="value.is_active" class="form-check-input">
                                        Visible
                                    </label>
                                    <input type="hidden" :name="'option_groups[' + gIndex + '][values][' + vIndex + '][is_active]'" :value="value.is_active ? '1' : '0'">
                                </div>
                                <div class="admin-span-sm-1 d-flex justify-content-end">
                                    <button type="button" @click="removeOptionValue(gIndex, vIndex)" class="btn btn-sm btn-outline-danger p-2 text-danger rounded-2" title="Remove choice">
                                        <svg class="admin-width-4 admin-height-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('productOptionsManager', () => ({
            variants: @json($initialVariants),
            optionGroups: @json($initialOptionGroups),
            errors: @json($errors->toArray()),
            inventoryMode: document.getElementById('inventory_mode')?.value || 'untracked',
            init() {
                this.variants.forEach(variant => {
                    variant.track_inventory ??= false;
                    variant.in_stock ??= true;
                    variant.stock_quantity ??= 0;
                    variant.reserved_quantity ??= 0;
                    variant.inventory_version ??= 0;
                    variant.plugin_attributes ??= {};
                    @foreach(app(\App\Services\ProductAttributesService::class)->fields('variant') as $entry)
                        @if(!$entry['legacy'])
                            variant.plugin_attributes[@js($entry['owner'])] ??= {};
                            variant.plugin_attributes[@js($entry['owner'])][@js($entry['field']->key)] ??= '';
                        @endif
                    @endforeach
                });
            },

            addVariant() {
                this.variants.push({
                    id: null,
                    name: '',
                    price: '',
                    discount_price: '',
                    servings: '',
                    plugin_attributes: {},
                    is_default: this.variants.length === 0,
                    is_active: true,
                    in_stock: true,
                    track_inventory: false,
                    stock_quantity: 0,
                    reserved_quantity: 0,
                    inventory_version: 0,
                });
                this.init();
            },
            removeVariant(index) {
                if (this.variants.length <= 1) {
                    alert('At least one variant is required.');
                    return;
                }
                const wasDefault = this.variants[index].is_default;
                this.variants.splice(index, 1);
                if (wasDefault && this.variants.length) {
                    this.setDefaultVariant(0);
                }
            },
            setDefaultVariant(index) {
                this.variants.forEach((variant, i) => {
                    variant.is_default = i === index;
                });
            },
            addOptionGroup() {
                this.optionGroups.push({
                    id: null,
                    name: '',
                    selection_type: 'multiple',
                    min_selections: 0,
                    max_selections: '',
                    is_required: false,
                    is_active: true,
                    step: '',
                    display: 'select',
                    help_text: '',
                    depends_on_value_id: '',
                    values: [{ id: null, name: '', price_adjustment: 0, is_active: true, swatch: '', input_label: '' }],
                });
            },
            // Saved choices of the other groups, for "show only when".
            dependencyChoices(gIndex) {
                return this.optionGroups.flatMap((group, i) => i === gIndex ? [] : group.values.filter(v => v.id).map(v => ({ id: v.id, label: group.name + ': ' + v.name })));
            },
            removeOptionGroup(index) {
                this.optionGroups.splice(index, 1);
            },
            onSelectionTypeChange(index) {
                if (this.optionGroups[index].selection_type === 'single') {
                    this.optionGroups[index].max_selections = 1;
                }
            },
            addOptionValue(groupIndex) {
                this.optionGroups[groupIndex].values.push({
                    id: null,
                    name: '',
                    price_adjustment: 0,
                    is_active: true,
                    swatch: '',
                    input_label: '',
                });
            },
            removeOptionValue(groupIndex, valueIndex) {
                if (this.optionGroups[groupIndex].values.length <= 1) {
                    alert('Each option group needs at least one choice.');
                    return;
                }
                this.optionGroups[groupIndex].values.splice(valueIndex, 1);
            },
        }));
    });
</script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('productOptionsManager', () => ({
            variants: @json($initialVariants),
            optionGroups: @json($initialOptionGroups),
            errors: @json($errors->toArray()),

            addVariant() {
                this.variants.push({
                    id: null,
                    name: '',
                    price: '',
                    discount_price: '',
                    servings: '',
                    is_default: this.variants.length === 0,
                    is_active: true,
                });
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
                    values: [{ id: null, name: '', price_adjustment: 0, is_active: true }],
                });
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

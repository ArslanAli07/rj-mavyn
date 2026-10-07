@props(['name', 'label' => 'Select media', 'value' => null, 'type' => null, 'multiple' => false])
<div x-data="{
    open: false, loading: false, files: [], error: '', selected: @js($multiple ? array_map('intval', array_values($value ?? [])) : ($value ? (int) $value : null)), next: null,
    multiple: @js((bool) $multiple),
    choose(file) {
        if (this.multiple) {
            if (!this.selected.includes(file.id)) this.selected.push(file.id);
        } else {
            this.selected = file.id; this.open = false;
        }
        this.$dispatch('media-selected', file);
    },
    move(index, offset) {
        const target = index + offset;
        if (target < 0 || target >= this.selected.length) return;
        const copy = [...this.selected];
        [copy[index], copy[target]] = [copy[target], copy[index]];
        this.selected = copy;
    },
    async load(url) {
        this.loading = true; this.error = '';
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Media could not be loaded.');
            const result = await response.json();
            this.files = result.data; this.next = result.next_page_url;
        } catch (e) { this.error = e.message; }
        finally { this.loading = false; }
    }
}" class="space-y-2" data-media-picker="{{ $name }}">
    @if($multiple)
        <input type="hidden" name="{{ $name }}_present" value="1">
        <template x-for="(id, index) in selected" :key="id">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <input type="hidden" name="{{ $name }}[]" :value="id">
                <span x-text="(index + 1) + '. Asset #' + id"></span>
                <button type="button" @click="move(index, -1)" :disabled="index === 0" class="underline disabled:opacity-40" aria-label="Move image earlier">Earlier</button>
                <button type="button" @click="move(index, 1)" :disabled="index === selected.length - 1" class="underline disabled:opacity-40" aria-label="Move image later">Later</button>
                <button type="button" @click="selected = selected.filter(value => value !== id)" class="underline" aria-label="Remove selected image">Remove</button>
            </div>
        </template>
    @else
        <input type="hidden" name="{{ $name }}" :value="selected || ''">
        <span x-show="selected" x-text="'Selected asset #' + selected" class="text-sm"></span>
    @endif
    <button type="button" class="btn-gold px-4 py-2" @click="open = !open; if (open) load(@js(route('admin.media.picker', array_filter(['type' => $type]))))">{{ $label }}</button>
    <button type="button" x-show="multiple ? selected.length : selected" x-cloak @click="selected = multiple ? [] : null" class="text-sm underline">Clear selection</button>
    <div x-show="open" x-cloak class="p-4 border rounded-xl bg-white">
        <p x-show="loading" role="status">Loading media…</p>
        <p x-show="error" x-text="error" role="alert" class="text-red-700"></p>
        <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
            <template x-for="file in files" :key="file.id">
                <button type="button" class="p-2 border rounded-lg" @click="choose(file)" :aria-pressed="multiple ? selected.includes(file.id) : selected === file.id">
                    <template x-if="file.mime_type.startsWith('image/')"><img :src="file.url" :alt="file.alt_text" class="h-20 w-full object-contain"></template>
                    <span x-text="file.title" class="block text-sm"></span>
                </button>
            </template>
        </div>
        <p x-show="!loading && !error && !files.length" class="text-sm">No public media available.</p>
        <button type="button" x-show="next" x-cloak @click="load(next)" class="underline mt-3">Next page</button>
        <button type="button" @click="open = false" class="underline mt-3 ml-3">Close</button>
    </div>
</div>

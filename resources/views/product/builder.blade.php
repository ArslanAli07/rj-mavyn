{{--
    Product builder pieces for custom products (Style -> Size -> Extras), driven by the product page's Alpine state.
    $part: 'tabs' | 'style' | 'size' | 'extras' | 'summary' | 'bar' | 'calc'.
    Needs: $product, $activeOptionGroups, $builderSteps; the size parts also use the page's size buckets,
    $customVariant, $customLead, $sizeFinder, $shipWindows and $shipsFree.
--}}
@once
<style>
    .rj-bld-steps {display:flex;margin:6px 0 0;border-bottom:1px solid var(--rj-line,#d8d8d8);}
    .rj-bld-step {flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px 6px;border:0;border-bottom:2px solid transparent;background:none;font:inherit;font-size:13px;font-weight:600;color:var(--rj-muted,#6f6f6f);cursor:pointer;margin-bottom:-1px;}
    .rj-bld-step__n {width:22px;height:22px;border-radius:50%;border:1px solid currentColor;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;}
    .rj-bld-step.is-active {color:var(--rj-ink,#17120f);border-bottom-color:var(--rj-accent,#17120f);}
    .rj-bld-step.is-active .rj-bld-step__n,.rj-bld-step.is-done .rj-bld-step__n {background:var(--rj-accent,#17120f);color:#fff;border-color:var(--rj-accent,#17120f);}
    .rj-bld-group {margin-bottom:18px;scroll-margin:120px;}
    .rj-bld-group.is-flagged {animation:rj-bld-flag 1.2s ease;}
    @keyframes rj-bld-flag {0%,40% {box-shadow:0 0 0 6px rgba(215,31,38,.18);background:rgba(215,31,38,.04);} 100% {box-shadow:0 0 0 6px transparent;}}
    .rj-bld-head {display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:8px;}
    .rj-bld-head .rj-form-label {margin:0;}
    .rj-bld-note,.rj-bld-optional {font-size:11px;font-weight:500;letter-spacing:0;text-transform:none;color:var(--rj-muted,#6f6f6f);}
    .rj-form-label .rj-bld-optional {float:right;}
    .rj-bld-note a,.rj-bld-note button {border:0;background:none;padding:0;font:inherit;color:var(--rj-ink,#17120f);text-decoration:underline;cursor:pointer;}
    .rj-bld-help {display:block;margin:0 0 10px;font-size:12px;line-height:1.5;color:var(--rj-muted,#6f6f6f);}
    .rj-bld-tiles {--cols:4;display:grid;grid-template-columns:repeat(var(--cols),minmax(0,1fr));gap:8px;}
    .rj-bld-tile {display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;min-height:46px;padding:8px 6px;border:1px solid var(--rj-line,#d8d8d8);background:#fff;font:inherit;font-size:12px;font-weight:700;line-height:1.25;text-align:center;color:var(--rj-ink,#17120f);cursor:pointer;}
    .rj-bld-tile:hover:not(:disabled) {border-color:var(--rj-ink,#17120f);}
    .rj-bld-tile.is-selected {border-color:var(--rj-ink,#17120f);box-shadow:inset 0 0 0 1px var(--rj-ink,#17120f);background:#f6f5f2;}
    .rj-bld-tile:disabled {opacity:.45;cursor:not-allowed;text-decoration:line-through;}
    .rj-bld-tile__price,.rj-bld-tile__sub {font-size:10px;font-weight:500;color:var(--rj-muted,#6f6f6f);}
    .rj-bld-tile--wide {width:100%;}
    .rj-bld-swatches {display:grid;grid-template-columns:repeat(auto-fill,minmax(36px,1fr));gap:6px;}
    .rj-bld-swatch {position:relative;aspect-ratio:1;border:1px solid var(--rj-line,#d8d8d8);border-radius:3px;padding:0;cursor:pointer;background-clip:padding-box;}
    .rj-bld-swatch:hover {border-color:var(--rj-ink,#17120f);}
    .rj-bld-swatch.is-selected {outline:2px solid var(--rj-ink,#17120f);outline-offset:2px;}
    .rj-bld-swatch.is-selected::after {content:attr(data-order);position:absolute;right:-7px;top:-7px;width:16px;height:16px;border-radius:50%;background:var(--rj-ink,#17120f);color:#fff;font-size:9px;font-weight:700;line-height:16px;text-align:center;}
    .rj-bld-swatch--text {display:flex;align-items:center;justify-content:center;padding:2px;font:inherit;font-size:8px;font-weight:800;line-height:1.1;text-transform:uppercase;background:#fff;color:var(--rj-ink,#17120f);}
    .rj-bld-swatch--more {background:var(--rj-ink,#17120f);color:#fff;font-size:10px;text-transform:none;}
    .rj-bld-slots {display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:10px;}
    .rj-bld-slot {min-height:36px;padding:6px;border:1px dashed var(--rj-line,#d8d8d8);font-size:11px;line-height:1.3;text-align:center;color:var(--rj-muted,#6f6f6f);display:flex;align-items:center;justify-content:center;gap:6px;}
    .rj-bld-slot.is-filled {border-style:solid;color:var(--rj-ink,#17120f);background:#fff;}
    .rj-bld-slot i {width:12px;height:12px;border-radius:50%;border:1px solid var(--rj-line,#d8d8d8);flex:none;}
    .rj-bld-input,.rj-bld-select {width:100%;box-sizing:border-box;height:42px;margin-top:10px;padding:0 12px;border:1px solid var(--rj-line,#d8d8d8);background:#fff;font:inherit;font-size:13px;}
    .rj-bld-drop {display:flex;align-items:center;justify-content:center;gap:8px;min-height:72px;padding:14px;border:1px dashed var(--rj-line,#b9b9b9);background:#fafafa;font-size:13px;color:var(--rj-muted,#6f6f6f);cursor:pointer;text-align:center;}
    .rj-bld-drop.is-over {border-color:var(--rj-ink,#17120f);background:#f1f0ec;}
    .rj-bld-file {position:absolute;left:0;bottom:0;width:1px;height:1px;opacity:0;pointer-events:none;overflow:hidden;}
    .rj-bld-files {margin:8px 0 0;padding:0;list-style:none;font-size:12px;}
    /* Size step */
    .rj-bld-seg {display:inline-flex;margin-bottom:10px;background:#f1f0ec;}
    .rj-bld-seg button {min-width:52px;padding:8px 14px;border:0;background:none;font:inherit;font-size:12px;font-weight:700;color:var(--rj-ink,#17120f);cursor:pointer;}
    .rj-bld-seg button.is-active {background:var(--rj-ink,#17120f);color:#fff;}
    .rj-bld-calc-link {display:block;width:100%;margin:10px 0 16px;padding:13px;border:0;background:#f1f0ec;font:inherit;font-size:12px;font-weight:700;color:var(--rj-ink,#17120f);cursor:pointer;}
    .rj-bld-calc-link u {text-underline-offset:2px;}
    .rj-bld-sub {display:block;margin:0 0 6px;font-size:11px;font-weight:700;}
    .rj-bld-callout {margin-top:8px;padding:10px 12px;background:#f6f5f2;font-size:12px;line-height:1.5;}
    /* Extras */
    .rj-bld-checks {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
    .rj-bld-checks--list {grid-template-columns:1fr;}
    .rj-bld-check {display:flex;align-items:center;gap:10px;min-height:42px;padding:8px 12px;border:1px solid var(--rj-line,#d8d8d8);background:#fff;font-size:12px;font-weight:600;line-height:1.3;cursor:pointer;}
    .rj-bld-check:hover {border-color:var(--rj-ink,#17120f);}
    .rj-bld-check.is-selected {border-color:var(--rj-ink,#17120f);background:#f6f5f2;}
    .rj-bld-check input {width:15px;height:15px;margin:0;flex:none;accent-color:var(--rj-ink,#17120f);}
    .rj-bld-check small {font-weight:500;color:var(--rj-muted,#6f6f6f);white-space:nowrap;}
    .rj-product-form label.rj-bld-check {flex-direction:row;gap:10px;font-size:12px;font-weight:600;letter-spacing:0;text-transform:none;text-align:left;}
    .rj-product-form .rj-bld-check input {width:15px;height:15px;padding:0;appearance:auto;-webkit-appearance:checkbox;}
    .rj-product-form label.rj-bld-sub {display:block;font-size:11px;letter-spacing:.06em;}
    .rj-bld-nested {padding:0 0 4px 12px;border-left:2px solid var(--rj-line,#d8d8d8);margin:-2px 0 4px 6px;scroll-margin:120px;}
    .rj-bld-nested .rj-bld-select {margin-top:6px;}
    .rj-bld-qty {display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px;}
    .rj-bld-qty .rj-form-label {margin:0;}
    /* Summary, nav and bar */
    .rj-bld-summary {margin:18px 0 0;padding:16px;background:#f1f0ec;}
    .rj-bld-summary__total {display:flex;align-items:baseline;gap:6px;font-size:28px;font-weight:800;line-height:1;}
    .rj-bld-summary__total small {font-size:11px;font-weight:600;color:var(--rj-muted,#6f6f6f);}
    .rj-bld-summary__row {display:flex;justify-content:space-between;gap:10px;margin-top:12px;font-size:12px;font-weight:700;}
    .rj-bld-summary__row button {border:0;background:none;padding:0;font:inherit;color:var(--rj-accent-deep,#1a5fb4);cursor:pointer;}
    .rj-bld-meter {height:4px;margin:8px 0 14px;background:#dcdad4;}
    .rj-bld-meter span {display:block;height:100%;background:var(--rj-ink,#17120f);transition:width .2s;}
    .rj-bld-nav {display:flex;gap:8px;}
    .rj-bld-nav button {min-height:48px;padding:0 16px;border:1px solid var(--rj-ink,#17120f);background:#fff;font:inherit;font-size:12px;font-weight:700;color:var(--rj-ink,#17120f);cursor:pointer;}
    .rj-bld-nav .is-primary {flex:1;background:var(--rj-ink,#17120f);color:#fff;font-size:15px;letter-spacing:.04em;text-transform:uppercase;}
    .rj-bld-ship {display:flex;align-items:center;gap:8px;margin-top:12px;font-size:12px;}
    .rj-bld-free {margin-top:10px;padding:11px 12px;background:#e3f4e8;color:#1e6b3a;font-size:12px;font-weight:600;}
    .rj-bld-heart {margin-left:auto;}
    .rj-bld-share-row {display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:12px;font-size:12px;color:var(--rj-muted,#6f6f6f);}
    .rj-bld-share {display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border:1px solid var(--rj-line,#d8d8d8);background:#fff;font:inherit;font-size:12px;font-weight:700;color:var(--rj-ink,#17120f);cursor:pointer;}
    .rj-bld-bar {position:fixed;left:0;right:0;bottom:0;z-index:40;display:flex;align-items:center;gap:16px;padding:10px max(16px,calc((100vw - 1280px) / 2));background:#fff;border-top:1px solid var(--rj-line,#d8d8d8);box-shadow:0 -4px 18px rgba(0,0,0,.06);}
    .rj-bld-bar__title {flex:1;min-width:0;font-size:14px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .rj-bld-bar__price {flex:0 1 340px;min-width:0;}
    .rj-bld-bar__price strong {display:block;font-size:15px;}
    .rj-bld-bar__price small {display:block;font-size:11px;line-height:1.35;color:var(--rj-muted,#6f6f6f);}
    .rj-bld-bar button {flex:none;min-height:46px;padding:0 26px;border:0;background:var(--rj-ink,#17120f);color:#fff;font:inherit;font-size:15px;font-weight:700;letter-spacing:.04em;cursor:pointer;}
    .rj-product:has(.rj-bld-bar) {padding-bottom:84px;}
    /* Size calculator */
    .rj-bld-calc {display:grid;gap:12px;}
    .rj-bld-calc__switches {display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;}
    .rj-bld-calc__grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;}
    .rj-bld-calc label {display:grid;gap:4px;font-size:12px;font-weight:700;}
    .rj-bld-calc label input {height:42px;padding:0 10px;border:1px solid var(--rj-line,#d8d8d8);font:inherit;font-size:14px;}
    .rj-bld-calc__result {padding:12px;background:#f6f5f2;font-size:13px;line-height:1.5;}
    .rj-bld-calc__result button {margin-top:8px;}
    @media (max-width: 767px) {
        .rj-bld-tiles {--cols:2 !important;}
        .rj-bld-tiles--sizes {--cols:3 !important;}
        .rj-bld-checks {grid-template-columns:1fr;}
        .rj-bld-slots {grid-template-columns:repeat(2,1fr);}
        .rj-bld-bar__title {display:none;}
        .rj-bld-bar button {padding:0 18px;font-size:13px;}
        .rj-bld-calc__grid {grid-template-columns:1fr;}
    }
</style>
@endonce

@php
    $usable = fn ($g) => $g->values->where('is_active', true)->filter(fn ($v) => $v->price_adjustment !== null);
    $stepGroups = fn ($key) => $activeOptionGroups->filter(fn ($g) => ($g->step ?? 'style') === $key && $usable($g)->isNotEmpty());
    $priceTag = fn ($amount) => (float) $amount != 0 ? '+ '.format_price($amount) : null;
@endphp

@if($part === 'tabs')
    <div class="rj-bld-steps" role="tablist" aria-label="Customisation steps">
        @foreach($builderSteps as $key => $label)
            <button type="button" role="tab" class="rj-bld-step" :class="{ 'is-active': step === '{{ $key }}', 'is-done': stepDone('{{ $key }}') && step !== '{{ $key }}' }" :aria-selected="step === '{{ $key }}'" @click="goStep('{{ $key }}')">
                <span class="rj-bld-step__n" x-text="stepDone('{{ $key }}') && step !== '{{ $key }}' ? '✓' : '{{ $loop->iteration }}'">{{ $loop->iteration }}</span>{{ $label }}
            </button>
        @endforeach
    </div>
@endif

@if($part === 'style' || $part === 'extras')
    @php
        $groups = $stepGroups($part);
        // Single-choice groups that open from a value of a checkbox group in this step (e.g. Gloves size under Matching Gloves) render nested under it.
        $listValueIds = $groups->filter(fn ($g) => $g->isMultiple() && $g->display !== 'swatches')->flatMap(fn ($g) => $usable($g)->pluck('id'))->all();
        $nested = $groups->filter(fn ($g) => $g->depends_on_value_id && in_array($g->depends_on_value_id, $listValueIds))->groupBy('depends_on_value_id');
        $nestedIds = $nested->flatten(1)->pluck('id')->all();
    @endphp

    {{-- The design upload sits under the first style choice (Product), as on the reference page --}}
    @if($part === 'style' && $product->allows_design_upload && $groups->reject(fn ($g) => in_array($g->id, $nestedIds, true))->isEmpty())
        @include('product.builder', ['part' => 'design'])
    @endif

    @foreach($groups as $group)
        @continue(in_array($group->id, $nestedIds, true))
        @php
            $gid = $group->id;
            $values = $usable($group);
            $required = $group->effectiveMinSelections() > 0;
            $max = $group->isMultiple() ? $group->effectiveMaxSelections() : null;
        @endphp
        <div class="rj-bld-group" id="bld-g{{ $gid }}" x-show="showGroup({{ $gid }})" @if($group->depends_on_value_id) x-cloak @endif>
            <div class="rj-bld-head">
                <span class="rj-form-label">{{ strtoupper($group->name) }}@if($required)<span class="rj-req-mark">*</span>@endif</span>
                @if($group->display === 'swatches' && $max)
                    <span class="rj-bld-note">Choose up to {{ $max }} for this design.</span>
                @elseif(! $required)
                    <span class="rj-bld-note">Optional</span>
                @endif
            </div>
            @if($group->help_text)<span class="rj-bld-help">{{ $group->help_text }}</span>@endif

            @if($group->display === 'swatches')
                @php $collapse = $values->count() > 11; @endphp
                <div class="rj-bld-swatches" role="group" aria-label="{{ $group->name }}">
                    @foreach($values as $val)
                        @php $hidden = $collapse && $loop->index >= 10; @endphp
                        <button type="button" @class(['rj-bld-swatch', 'rj-bld-swatch--text' => ! $val->swatch || strcasecmp($val->name, 'Default') === 0])
                                :class="{ 'is-selected': isSelected({{ $gid }}, {{ $val->id }}) }" :data-order="swatchOrder({{ $gid }}, {{ $val->id }})"
                                @if($val->swatch && strcasecmp($val->name, 'Default') !== 0) style="background-color: {{ $val->swatch }}" @endif
                                @if($hidden) x-show="moreColors[{{ $gid }}] || isSelected({{ $gid }}, {{ $val->id }})" x-cloak @endif
                                title="{{ $val->name }}{{ $priceTag($val->price_adjustment) ? ' ('.$priceTag($val->price_adjustment).')' : '' }}" aria-label="{{ $val->name }}" :aria-pressed="isSelected({{ $gid }}, {{ $val->id }})"
                                @click="toggleOption({{ $gid }}, {{ $val->id }})">@if(! $val->swatch || strcasecmp($val->name, 'Default') === 0){{ strcasecmp($val->name, 'Default') === 0 ? 'Default color' : $val->name }}@endif</button>
                    @endforeach
                    @if($collapse)
                        <button type="button" class="rj-bld-swatch rj-bld-swatch--text rj-bld-swatch--more" @click="moreColors[{{ $gid }}] = !moreColors[{{ $gid }}]" :aria-expanded="!!moreColors[{{ $gid }}]" x-text="moreColors[{{ $gid }}] ? 'Less' : 'More'">More</button>
                    @endif
                </div>
                @if($max)
                    <div class="rj-bld-slots" aria-live="polite">
                        @for($slot = 0; $slot < $max; $slot++)
                            <div class="rj-bld-slot" :class="{ 'is-filled': slotValue({{ $gid }}, {{ $slot }}) }">
                                <template x-if="slotValue({{ $gid }}, {{ $slot }})"><span style="display:contents"><i :style="slotValue({{ $gid }}, {{ $slot }}).swatch ? 'background:' + slotValue({{ $gid }}, {{ $slot }}).swatch : ''"></i><span x-text="slotValue({{ $gid }}, {{ $slot }}).name"></span></span></template>
                                <template x-if="!slotValue({{ $gid }}, {{ $slot }})"><span>Color {{ $slot + 1 }}</span></template>
                            </div>
                        @endfor
                    </div>
                @endif
            @elseif($group->isMultiple())
                {{-- Add-ons and matching gear: priced checkboxes; a value with a follow-up question (its size) gets it right underneath --}}
                @php $asList = $values->contains(fn ($v) => $nested->has($v->id)); @endphp
                <div @class(['rj-bld-checks', 'rj-bld-checks--list' => $asList]) role="group" aria-label="{{ $group->name }}">
                    @foreach($values as $val)
                        <label class="rj-bld-check" :class="{ 'is-selected': isSelected({{ $gid }}, {{ $val->id }}) }">
                            <input type="checkbox" :checked="isSelected({{ $gid }}, {{ $val->id }})" @change="toggleOption({{ $gid }}, {{ $val->id }})">
                            <span>{{ $val->name }}@if($priceTag($val->price_adjustment)) <small>{{ $priceTag($val->price_adjustment) }}</small>@endif</span>
                        </label>
                        @foreach($nested->get($val->id, collect()) as $child)
                            <div class="rj-bld-nested" id="bld-g{{ $child->id }}" x-show="showGroup({{ $child->id }})" x-cloak>
                                <label class="rj-bld-sub" for="bld-select-{{ $child->id }}" style="margin-top:6px">{{ $child->name }}@if($child->effectiveMinSelections() > 0)<span class="rj-req-mark">*</span>@endif</label>
                                <select id="bld-select-{{ $child->id }}" class="rj-bld-select" :value="selectedOptionIds[{{ $child->id }}] ?? ''" @change="toggleOption({{ $child->id }}, $event.target.value)">
                                    <option value="" disabled selected>-- Please select --</option>
                                    @foreach($usable($child) as $childVal)
                                        <option value="{{ $childVal->id }}">{{ $childVal->name }}{{ $priceTag($childVal->price_adjustment) ? ' '.$priceTag($childVal->price_adjustment) : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            @else
                <div class="rj-bld-tiles" style="--cols: {{ min(max($values->count(), 3), 4) }}" role="radiogroup" aria-label="{{ $group->name }}">
                    @foreach($values as $val)
                        <button type="button" class="rj-bld-tile" role="radio" :class="{ 'is-selected': isSelected({{ $gid }}, {{ $val->id }}) }" :aria-checked="isSelected({{ $gid }}, {{ $val->id }})" @click="toggleOption({{ $gid }}, {{ $val->id }})">
                            <span>{{ $val->name }}</span>
                            @if($priceTag($val->price_adjustment))<span class="rj-bld-tile__price">{{ $priceTag($val->price_adjustment) }}</span>@endif
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Choosing a value can ask for text, e.g. the Pantone colour for a custom colour --}}
            @foreach($values->filter(fn ($v) => $v->input_label) as $val)
                <div x-show="isSelected({{ $gid }}, {{ $val->id }})" x-cloak>
                    <input type="text" class="rj-bld-input" maxlength="120" x-model="texts[{{ $val->id }}]" placeholder="{{ $val->input_label }}" aria-label="{{ $val->input_label }}" aria-required="true">
                </div>
            @endforeach
        </div>
        @if($part === 'style' && $loop->first && $product->allows_design_upload)
            @include('product.builder', ['part' => 'design'])
        @endif
    @endforeach
@endif

@if($part === 'design')
        <div class="rj-bld-group" style="position:relative">
            <span class="rj-form-label">DESIGN</span>
            <span class="rj-bld-help">PNG, JPG or PDF, up to {{ \App\Services\ProductCustomizationService::MAX_KB / 1024 }} MB each ({{ \App\Services\ProductCustomizationService::MAX_FILES }} files at most). No file? Describe your design in Order Instructions.</span>
            <label class="rj-bld-drop" :class="{ 'is-over': dragging }" for="design-files"
                   @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                   @drop.prevent="dragging = false; $refs.designFiles.files = $event.dataTransfer.files; onFiles($refs.designFiles)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 12.5 12.5 21a5.5 5.5 0 0 1-8-8l9-9a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/></svg>
                <span>Add files or drop here</span>
            </label>
            <input id="design-files" x-ref="designFiles" class="rj-bld-file" type="file" name="design_files[]" multiple accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" @change="onFiles($event.target)">
            <ul class="rj-bld-files" x-show="fileNames.length" x-cloak><template x-for="name in fileNames" :key="name"><li x-text="name"></li></template></ul>
        </div>
@endif

@if($part === 'size')
    @php
        // "US 32 · EU 42 · XS" shows as EU 42 over "US 32 · XS"; other names show as they are.
        $sizeLabel = function ($variant) {
            $parts = array_map('trim', explode('·', $variant->display_name));
            $eu = collect($parts)->first(fn ($p) => str_starts_with($p, 'EU '));

            return $eu ? [$eu, implode(' · ', array_values(array_diff($parts, [$eu])))] : [$variant->display_name, null];
        };
        $buckets = array_filter(['men' => $menVariants, 'women' => $womenVariants, 'standard' => $standardVariants], fn ($b) => $b->isNotEmpty());
        $baseVariant = $menVariants->first() ?? $standardVariants->first() ?? $womenVariants->first();
        $customExtra = $customVariant && $baseVariant ? (float) $customVariant->price - (float) $baseVariant->price : 0;
        $hasChart = $hasTables || ! empty($product->size_chart['rows']);
    @endphp
    <div class="rj-bld-group" id="bld-size">
        <div class="rj-bld-head">
            <span class="rj-form-label">SIZE<span class="rj-req-mark">*</span></span>
            @if($hasChart)<span class="rj-bld-note"><button type="button" @click="openSizeModal = true">Size guide</button></span>@endif
        </div>

        @if(count(array_intersect_key($buckets, ['men' => 1, 'women' => 1])) > 1)
            <div class="rj-bld-seg" role="tablist" aria-label="Fit">
                <button type="button" role="tab" :class="{ 'is-active': sizeTab === 'men' }" :aria-selected="sizeTab === 'men'" @click="sizeTab = 'men'">Men</button>
                <button type="button" role="tab" :class="{ 'is-active': sizeTab === 'women' }" :aria-selected="sizeTab === 'women'" @click="sizeTab = 'women'">Women</button>
            </div>
        @endif

        @foreach($buckets as $bucketKey => $bucket)
            <div class="rj-bld-tiles rj-bld-tiles--sizes" role="radiogroup" aria-label="{{ ucfirst($bucketKey) }} sizes" @if($bucketKey !== 'standard') x-show="sizeTab === '{{ $bucketKey }}'" @if($bucketKey === 'women') x-cloak @endif @else style="margin-top:8px" @endif>
                @foreach($bucket as $variant)
                    @php [$main, $sub] = $sizeLabel($variant); @endphp
                    <button type="button" class="rj-bld-tile" role="radio" :class="{ 'is-selected': Number(selectedVariantId) === {{ $variant->id }} }" :aria-checked="Number(selectedVariantId) === {{ $variant->id }}"
                            :disabled="variants.find(v => v.id === {{ $variant->id }})?.available_quantity === 0" @click="pickSize({{ $variant->id }})">
                        <span>{{ $main }}</span>
                        @if($sub)<span class="rj-bld-tile__sub">{{ $sub }}</span>@endif
                    </button>
                @endforeach
            </div>
        @endforeach

        @if(! empty($sizeFinder))
            <button type="button" class="rj-bld-calc-link" aria-haspopup="dialog" @click="calcOpen = true; calcResult = null; calc.gender = sizeTab === 'women' ? 'women' : 'men'">Not sure? <u>Calculate your size</u></button>
        @endif

        @if($customVariant)
            <span class="rj-bld-sub" @if(empty($sizeFinder)) style="margin-top:14px" @endif>Or made to your measurements</span>
            <button type="button" class="rj-bld-tile rj-bld-tile--wide" role="radio" :class="{ 'is-selected': isCustomSize }" :aria-checked="isCustomSize" @click="pickSize({{ $customVariant->id }})">
                <span>Custom Size</span>
                <span class="rj-bld-tile__sub">@if($customExtra > 0)+ {{ format_price($customExtra) }}. @endif Made in {{ $customLead }}</span>
            </button>
            <div class="rj-bld-callout" x-show="isCustomSize" x-cloak>
                After checkout you send us your body measurements and we cut the suit to them.
                <a class="rj-textlink" href="{{ route('guide.how-to-measure') }}" target="_blank" rel="noopener">How to measure</a>
            </div>
        @endif
    </div>
@endif

@if($part === 'summary')
    <div class="rj-bld-summary" aria-live="polite">
        <div class="rj-bld-summary__total"><span x-text="formatMoney(unitPrice * quantity)">{{ format_price($product->effective_price) }}</span><small>{{ app(\Mavyn\Contracts\Pricing\PriceBook::class)->context()->currency }}</small></div>
        <div class="rj-bld-summary__row">
            <span x-text="progress.done + ' of ' + progress.total + ' chosen'"></span>
            <button type="button" x-show="nextItem" x-cloak @click="goToItem(nextItem)" x-text="nextItem ? 'Next: ' + nextItem.label : ''"></button>
        </div>
        <div class="rj-bld-meter" aria-hidden="true"><span :style="'width:' + (progress.total ? Math.round(progress.done / progress.total * 100) : 100) + '%'"></span></div>
        <div class="rj-bld-nav">
            <button type="button" x-show="stepIndex() > 0" x-cloak @click="goStep(stepKeys[stepIndex() - 1])">Back</button>
            <button type="button" class="is-primary" @click="primaryAction()" x-text="primaryLabel()">Next</button>
        </div>
    </div>
@endif

@if($part === 'bar')
    <div class="rj-bld-bar" role="region" aria-label="Order summary">
        <div class="rj-bld-bar__title">{{ $product->title }}</div>
        <div class="rj-bld-bar__price">
            <strong x-text="formatMoney(unitPrice * quantity)">{{ format_price($product->effective_price) }}</strong>
            <small x-text="missingLabels.length ? 'Still to choose: ' + missingLabels.join(', ') + '.' : 'All set. Ready to add to cart.'"></small>
        </div>
        <button type="button" @click="submitBuilder()">ADD TO CART</button>
    </div>
@endif

@if($part === 'calc' && ! empty($sizeFinder))
    <div class="rj-size-modal-backdrop" x-show="calcOpen" x-cloak @click.self="calcOpen = false">
        <div class="rj-size-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bld-calc-title" style="max-width:560px">
            <div class="rj-size-modal-header">
                <div>
                    <h3 class="rj-size-modal-title" id="bld-calc-title">CALCULATE YOUR SIZE</h3>
                    <p class="rj-size-modal-subtitle">For {{ $product->title }}. Measure your body over light clothing with a soft tape.</p>
                </div>
                <button type="button" class="rj-size-modal-close" @click="calcOpen = false" aria-label="Close size calculator">&times;</button>
            </div>
            <form class="rj-size-modal-body rj-bld-calc" @submit.prevent="runCalc()">
                <div class="rj-bld-calc__switches">
                    @if(count($sizeFinder) > 1)
                        <div class="rj-bld-seg" style="margin:0">
                            @foreach(array_keys($sizeFinder) as $fit)
                                <button type="button" :class="{ 'is-active': calc.gender === '{{ $fit }}' }" @click="calc.gender = '{{ $fit }}'; calcResult = null">{{ ucfirst($fit) }}</button>
                            @endforeach
                        </div>
                    @endif
                    <div class="rj-bld-seg" style="margin:0">
                        <button type="button" :class="{ 'is-active': calc.unit === 'cm' }" @click="calc.unit = 'cm'; calcResult = null">cm</button>
                        <button type="button" :class="{ 'is-active': calc.unit === 'in' }" @click="calc.unit = 'in'; calcResult = null">inches</button>
                    </div>
                </div>
                <div class="rj-bld-calc__grid">
                    <label>Chest * <input type="number" step="0.1" min="1" inputmode="decimal" x-model="calc.chest" required :placeholder="calc.unit"></label>
                    <label>Height * <input type="number" step="0.1" min="1" inputmode="decimal" x-model="calc.height" required :placeholder="calc.unit"></label>
                    <label>Waist <input type="number" step="0.1" min="1" inputmode="decimal" x-model="calc.waist" :placeholder="'optional, ' + calc.unit"></label>
                    <label>Hip <input type="number" step="0.1" min="1" inputmode="decimal" x-model="calc.hip" :placeholder="'optional, ' + calc.unit"></label>
                </div>
                <span class="rj-bld-help" style="margin:0">Chest: around the fullest part, under the arms. Waist: around your natural waist. Hip: around the widest part of the seat.</span>
                <button type="submit" class="rj-button rj-button--accent">CALCULATE MY SIZE</button>
                <div class="rj-bld-calc__result" x-show="calcResult" x-cloak aria-live="polite">
                    <template x-if="calcResult && calcResult.error"><span x-text="calcResult.error"></span></template>
                    <template x-if="calcResult && calcResult.id">
                        <div>
                            We suggest <strong x-text="calcResult.name"></strong>.
                            <span x-show="calcResult.note" x-text="' ' + calcResult.note"></span>
                            <div><button type="button" class="rj-button rj-button--accent" @click="useCalc(calcResult.id)">Choose this size</button></div>
                        </div>
                    </template>
                    <template x-if="calcResult && calcResult.custom">
                        <div>
                            <span x-text="calcResult.text"></span> Custom Size will be cut to your measurements.
                            @if($customVariant)<div><button type="button" class="rj-button rj-button--accent" @click="useCalc(null)">Choose Custom Size</button></div>@endif
                        </div>
                    </template>
                </div>
                <span class="rj-bld-help" style="margin:0">A suggestion from your measurements and our size chart. Nothing you type here is saved.</span>
            </form>
        </div>
    </div>
@endif

@php
    $store = setting('site_name', config('site.name', 'RJ Motorbike'));
    // Field metadata for the client: which garments/fits need each measurement.
    $fieldMeta = [];
    foreach ($groups as $groupKey => $group) {
        foreach ($group['fields'] as $key => $field) {
            $fieldMeta[$key] = ['for' => $field['for'], 'women' => (bool) ($field['women'] ?? false)];
        }
    }
    $errorStep = $errors->hasAny(['order_reference', 'name', 'email', 'garment', 'fit', 'unit']) ? 1
        : (collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'm.')) ? 2 : ($errors->any() ? 3 : 1));
@endphp
<x-app-layout :title="'Send Your Measurements — '.$store" :meta-description="'Send your body measurements for a '.$store.' Custom Size or custom-made order.'" page-key="measurement-form">
    <div class="gd">
        <div class="rj-container">
            <nav class="rj-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><span>/</span><a href="{{ route('guide.how-it-works') }}">How it works</a><span>/</span><span aria-current="page">Measurement form</span>
            </nav>

            @include('guide._partials', ['noTabs' => true])
            <header class="gd-head gd-head--center">
                <h1 class="gd-title"><span>Measurement form</span></h1>
                <p class="gd-lede">Use this form to send your body measurements for a Custom Size or Custom Made order. Add your order number and the email you ordered with, so we can match them to your order. Measure over thin clothing and follow our <a class="gd-link" href="{{ route('guide.how-to-measure') }}">measuring guide</a>.</p>
            </header>

            @if(session('measurement_success'))
                <div class="gd-alert" role="status">{{ session('measurement_success') }}</div>
            @endif
            @if($errors->any())
                <div class="gd-alert gd-alert--error" role="alert">Please check the highlighted fields and try again.</div>
            @endif

            <form method="POST" action="{{ route('guide.measurement-form.store') }}" enctype="multipart/form-data" novalidate
                  x-data="{
                      step: {{ $errorStep }},
                      garment: @js(old('garment', 'suit')),
                      fit: @js(old('fit', 'men')),
                      unit: @js(old('unit', 'cm')),
                      meta: @js($fieldMeta),
                      needs(key) { const f = this.meta[key]; return f.for.includes(this.garment) && (!f.women || this.fit === 'women'); },
                      unitFor(key) { return key === 'weight' ? (this.unit === 'cm' ? 'kg' : 'lb') : (this.unit === 'cm' ? 'cm' : 'in'); },
                      next() {
                          const panel = this.$refs['step' + this.step];
                          // Shown = not disabled and no ancestor inside this step hidden by x-show (works in background tabs too).
                          const shown = el => { if (el.disabled) return false; for (let n = el; n && n !== panel; n = n.parentElement) { if (getComputedStyle(n).display === 'none') return false; } return true; };
                          const fields = [...panel.querySelectorAll('input, select, textarea')].filter(el => el.dataset.required === '1' && shown(el));
                          const bad = fields.find(el => el.type === 'checkbox' ? !el.checked : String(el.value).trim() === '' || (el.type === 'email' && !el.checkValidity()) || (el.type === 'number' && !el.checkValidity()));
                          if (bad) { bad.setCustomValidity(bad.type === 'checkbox' ? 'Please tick to confirm.' : 'Please fill this in.'); bad.reportValidity(); bad.addEventListener('input', () => bad.setCustomValidity(''), { once: true }); return false; }
                          return true;
                      }
                  }"
                  @submit="if (!next()) $event.preventDefault()">
                @csrf
                <x-honeypot />

                <ol class="gd-progress" aria-label="Progress">
                    <li :class="{ 'is-active': step >= 1 }">1 · Your order</li>
                    <li :class="{ 'is-active': step >= 2 }">2 · Measurements</li>
                    <li :class="{ 'is-active': step >= 3 }">3 · Photos &amp; check</li>
                </ol>

                {{-- Step 1: order and garment --}}
                <section x-ref="step1" x-show="step === 1" @if($errorStep !== 1) x-cloak @endif>
                    <div class="gd-form-grid">
                        <div class="gd-field">
                            <label for="order_reference">Order number</label>
                            <input id="order_reference" name="order_reference" class="gd-input" data-required="1" maxlength="64" placeholder="ORD-…" value="{{ old('order_reference', $prefillOrder) }}" autocomplete="off">
                            <span class="gd-help">From your order confirmation email.</span>
                            @error('order_reference')<span class="gd-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="gd-field">
                            <label for="email">Email used for the order</label>
                            <input id="email" name="email" type="email" class="gd-input" data-required="1" maxlength="255" value="{{ old('email') }}" autocomplete="email">
                            @error('email')<span class="gd-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="gd-field">
                            <label for="name">Name of the person measured</label>
                            <input id="name" name="name" class="gd-input" data-required="1" maxlength="120" value="{{ old('name') }}" autocomplete="name">
                            @error('name')<span class="gd-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="gd-field">
                            <label for="garment">Garment</label>
                            <select id="garment" name="garment" class="gd-input" x-model="garment" data-required="1">
                                @foreach($garments as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('garment')<span class="gd-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="gd-field">
                            <span class="gd-field" style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Fit</span>
                            <div class="gd-seg" role="radiogroup" aria-label="Fit">
                                <label><input type="radio" name="fit" value="men" x-model="fit"><span>Men</span></label>
                                <label><input type="radio" name="fit" value="women" x-model="fit"><span>Women</span></label>
                            </div>
                            @error('fit')<span class="gd-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="gd-field">
                            <span class="gd-field" style="font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">Units</span>
                            <div class="gd-seg" role="radiogroup" aria-label="Units">
                                <label><input type="radio" name="unit" value="cm" x-model="unit"><span>Centimetres &amp; kg</span></label>
                                <label><input type="radio" name="unit" value="in" x-model="unit"><span>Inches &amp; lb</span></label>
                            </div>
                            @error('unit')<span class="gd-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="gd-btns"><button type="button" class="gd-btn" @click="if (next()) step = 2">Next: measurements</button></div>
                </section>

                {{-- Step 2: only the measurements this garment and fit need --}}
                <section x-ref="step2" x-show="step === 2" @if($errorStep !== 2) x-cloak @endif>
                    <p class="gd-help" style="margin:0 0 16px">Fill every field shown. Not sure how? See the <a class="gd-link" href="{{ route('guide.how-to-measure') }}" target="_blank" rel="noopener">measuring guide</a> (opens in a new tab).</p>
                    @foreach($groups as $groupKey => $group)
                        <fieldset style="border:0;padding:0;margin:0 0 22px" x-show="{{ \Illuminate\Support\Js::from(array_keys($group['fields'])) }}.some(k => needs(k))">
                            <legend class="gd-eyebrow" style="margin-bottom:12px">{{ $group['title'] }}</legend>
                            <div class="gd-form-grid gd-form-grid--3">
                                @foreach($group['fields'] as $key => $field)
                                    <div class="gd-field" x-show="needs(@js($key))">
                                        <label for="m_{{ $key }}">{{ $field['label'] }} (<span x-text="unitFor(@js($key))">{{ $key === 'weight' ? 'kg' : 'cm' }}</span>)</label>
                                        <input id="m_{{ $key }}" name="m[{{ $key }}]" type="number" inputmode="decimal" step="0.1" min="0" class="gd-input" data-required="1" :disabled="!needs(@js($key))" value="{{ old('m.'.$key) }}" aria-describedby="h_{{ $key }}">
                                        <span class="gd-help" id="h_{{ $key }}">{{ $field['help'] }}</span>
                                        @error('m.'.$key)<span class="gd-error">{{ $message }}</span>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <div class="gd-btns">
                        <button type="button" class="gd-btn gd-btn--ghost" @click="step = 1">Back</button>
                        <button type="button" class="gd-btn" @click="if (next()) step = 3">Next: photos &amp; check</button>
                    </div>
                </section>

                {{-- Step 3: notes, photos, confirmation --}}
                <section x-ref="step3" x-show="step === 3" @if($errorStep !== 3) x-cloak @endif>
                    <div class="gd-form-grid">
                        <div class="gd-field" style="grid-column:1/-1">
                            <label for="notes">Additional instructions (optional)</label>
                            <textarea id="notes" name="notes" class="gd-input" maxlength="2000" placeholder="Riding position, preferred fit (snug or relaxed), anything we should know.">{{ old('notes') }}</textarea>
                            @error('notes')<span class="gd-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="gd-field" style="grid-column:1/-1">
                            <label for="photos">Photos from the front, side and back (optional)</label>
                            <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="gd-input" style="padding-top:10px">
                            <span class="gd-help">Up to {{ $photoLimits['max'] }} photos, JPG, PNG or WEBP, {{ (int) ($photoLimits['max_kb'] / 1024) }} MB each. Only our team can see them.</span>
                            @error('photos')<span class="gd-error">{{ $message }}</span>@enderror
                            @foreach($errors->get('photos.*') as $messages)<span class="gd-error">{{ $messages[0] }}</span>@endforeach
                        </div>
                        <label style="grid-column:1/-1;display:flex;gap:10px;align-items:flex-start;line-height:1.55;font-size:14px">
                            <input type="checkbox" name="confirm" value="1" data-required="1" @checked(old('confirm')) style="margin-top:4px">
                            <span>I confirm that these measurements and details are correct. I understand that a piece made to incorrect measurements I supplied may not fit.</span>
                        </label>
                        @error('confirm')<span class="gd-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="gd-btns">
                        <button type="button" class="gd-btn gd-btn--ghost" @click="step = 2">Back</button>
                        <button type="submit" class="gd-btn">Send measurements</button>
                    </div>
                </section>
            </form>
            <div style="height:40px"></div>
        </div>
    </div>
</x-app-layout>

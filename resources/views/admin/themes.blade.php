<x-admin-layout title="Themes">
    <div class="max-w-5xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold">Storefront themes</h1>
        <p>Active: {{ $selection->active_theme }}. Previous: {{ $selection->previous_theme ?? 'None' }}. Switching preserves settings and store content.</p>
        @if(session('success')) <p role="status">{{ session('success') }}</p> @endif
        @if($errors->any()) <div role="alert">{{ $errors->first() }}</div> @endif
        @foreach($themes as $id => $package)
            <section class="bg-white rounded-xl border p-6 space-y-4">
                <h2 class="font-bold text-lg">{{ $package['manifest']['name'] }} <small>{{ $package['version'] }}</small></h2>
                <p>{{ $id }} @if($package['manifest']['parent'] ?? null) · Parent: {{ $package['manifest']['parent'] }} @endif</p>
                @if($unsupported[$id])<p>Retained settings unavailable in this version: {{ implode(', ', $unsupported[$id]) }}.</p>@endif
                @if(auth('admin')->user()->hasPermission('themes.manage'))
                    <form method="POST" action="{{ route('admin.themes.activate', $id) }}">@csrf
                        <button class="btn-primary" @disabled($selection->active_theme === $id)>{{ $selection->active_theme === $id ? 'Active' : 'Activate' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.themes.settings', $id) }}" class="space-y-3">@csrf @method('PUT')
                        @foreach($definitions[$id] as $key => $field)
                            <label class="block">{{ str_replace('_', ' ', ucfirst($key)) }}
                                @if($field->definition['type'] === 'boolean')
                                    <input type="hidden" name="settings[{{ $key }}]" value="0">
                                    <input type="checkbox" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key, $values[$id][$key]))>
                                @else
                                    <input class="input-luxury" name="settings[{{ $key }}]" type="{{ match($field->definition['type']) { 'color' => 'color', 'integer' => 'number', default => 'text' } }}" value="{{ old('settings.'.$key, $values[$id][$key]) }}">
                                @endif
                            </label>
                        @endforeach
                        <button class="btn-primary">Save settings</button>
                        <button class="btn-secondary" formaction="{{ route('admin.themes.preview', $id) }}" name="_method" value="POST">Preview draft</button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
</x-admin-layout>

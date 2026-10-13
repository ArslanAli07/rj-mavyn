<x-admin-layout title="Themes">
    <div class="admin-max-width-5xl mx-auto admin-stack-6">
        <h1 class="admin-page-title">Storefront themes</h1>
        <p>Active: {{ $selection->active_theme }}. Previous: {{ $selection->previous_theme ?? 'None' }}. Switching preserves settings and store content.</p>
        @if(session('success')) <p role="status">{{ session('success') }}</p> @endif
        @if($errors->any()) <div role="alert">{{ $errors->first() }}</div> @endif
        @foreach($themes as $id => $package)
            <section class="bg-white rounded-2 border p-4 admin-stack-4">
                <h2 class="admin-section-title">{{ $package['manifest']['name'] }} <small>{{ $package['version'] }}</small></h2>
                <p>{{ $id }} @if($package['manifest']['parent'] ?? null) · Parent: {{ $package['manifest']['parent'] }} @endif</p>
                @if($unsupported[$id])<p>Retained settings unavailable in this version: {{ implode(', ', $unsupported[$id]) }}.</p>@endif
                @if(auth('admin')->user()->hasPermission('themes.manage'))
                    <form method="POST" action="{{ route('admin.themes.activate', $id) }}">@csrf
                        <button class="btn btn-primary" @disabled($selection->active_theme === $id)>{{ $selection->active_theme === $id ? 'Active' : 'Activate' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.themes.settings', $id) }}" class="admin-stack-3">@csrf @method('PUT')
                        @foreach($definitions[$id] as $key => $field)
                            <label class="d-block">{{ str_replace('_', ' ', ucfirst($key)) }}
                                @if($field->definition['type'] === 'boolean')
                                    <input type="hidden" name="settings[{{ $key }}]" value="0">
                                    <input type="checkbox" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key, $values[$id][$key])) class="form-check-input">
                                @else
                                    <input class="form-control" name="settings[{{ $key }}]" type="{{ match($field->definition['type']) { 'color' => 'color', 'integer' => 'number', default => 'text' } }}" value="{{ old('settings.'.$key, $values[$id][$key]) }}">
                                @endif
                            </label>
                        @endforeach
                        <button class="btn btn-primary">Save settings</button>
                        <button class="btn btn-outline-secondary" formaction="{{ route('admin.themes.preview', $id) }}" name="_method" value="POST">Preview draft</button>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
</x-admin-layout>

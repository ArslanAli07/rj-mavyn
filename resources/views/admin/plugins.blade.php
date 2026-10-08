<x-admin-layout title="Plugins">
    <h1 class="text-2xl font-heading font-bold mb-4">Plugins</h1>
    @if($errors->any())
        <div role="alert" class="mb-4 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    @forelse($plugins as $id => $plugin)
        @php($active = (bool) ($states->get($id)?->active ?? false))
        <section class="bg-white border rounded-xl p-5 mb-4">
            <h2 class="font-bold">{{ $plugin['manifest']['name'] }}</h2>
            <p class="text-sm mb-3">{{ $plugin['version'] }} · {{ $active ? 'Active' : 'Disabled' }}</p>
            @if(auth('admin')->user()->hasPermission('extensions.manage'))
                <form method="POST" action="{{ route('admin.extensions.update', $id) }}" class="mb-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="active" value="{{ $active ? '0' : '1' }}">
                    <button class="btn-primary" type="submit">{{ $active ? 'Deactivate' : 'Activate' }}</button>
                </form>
                @if($active && $definitions[$id])
                    <form method="POST" action="{{ route('admin.extensions.settings', $id) }}" class="space-y-3">
                        @csrf @method('PUT')
                        @foreach($definitions[$id] as $key => $definition)
                            @if(auth('admin')->user()->hasPermission($definition->definition['permission'] ?? 'extensions.manage'))
                                <div>
                                    <label for="{{ $id }}-{{ $key }}" class="label-luxury">{{ str_replace('_', ' ', ucfirst($key)) }}</label>
                                    @if($definition->definition['type'] === 'boolean')
                                        <input type="hidden" name="settings[{{ $key }}]" value="0">
                                        <input id="{{ $id }}-{{ $key }}" type="checkbox" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key, $values[$id][$key]))>
                                    @else
                                        <input id="{{ $id }}-{{ $key }}" class="input-luxury" type="{{ $definition->secret() ? 'password' : 'text' }}" name="settings[{{ $key }}]" value="{{ $definition->secret() ? '' : old('settings.'.$key, $values[$id][$key]) }}" autocomplete="{{ $definition->secret() ? 'new-password' : 'off' }}">
                                        @if($definition->secret())<p class="text-xs">Leave blank to keep the saved value.</p>@endif
                                    @endif
                                </div>
                            @endif
                        @endforeach
                        <button type="submit" class="btn-primary">Save settings</button>
                    </form>
                @endif
            @endif
        </section>
    @empty
        <p>No plugins are installed.</p>
    @endforelse
</x-admin-layout>

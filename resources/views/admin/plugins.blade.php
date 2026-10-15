<x-admin-layout title="Plugins">
    <h1 class="mb-3 admin-page-title">Plugins</h1>
    @if($errors->any())
        <div role="alert" class="mb-3 text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif
    @forelse($plugins as $id => $plugin)
        @php($active = (bool) ($states->get($id)?->active ?? false))
        <section class="bg-white border rounded-2 p-4 mb-3">
            <h2 class="admin-section-title">{{ $plugin['manifest']['name'] }}</h2>
            <p class="small mb-3">{{ $plugin['version'] }} · {{ $active ? 'Active' : 'Disabled' }}</p>
            @if($active && \Illuminate\Support\Facades\Route::has('plugin.'.$id.'.admin.index') && auth('admin')->user()->hasPermission($id.'.manage'))
                <a class="btn btn-outline-primary mb-3" href="{{ route('plugin.'.$id.'.admin.index') }}">Manage {{ $plugin['manifest']['name'] }}</a>
            @endif
            @if(auth('admin')->user()->hasPermission('extensions.manage'))
                <form method="POST" action="{{ route('admin.extensions.update', $id) }}" class="mb-3">
                    @csrf @method('PUT')
                    <input type="hidden" name="active" value="{{ $active ? '0' : '1' }}">
                    <button class="btn btn-primary" type="submit">{{ $active ? 'Deactivate' : 'Activate' }}</button>
                </form>
                @if($active && $definitions[$id])
                    <form method="POST" action="{{ route('admin.extensions.settings', $id) }}" class="admin-stack-3">
                        @csrf @method('PUT')
                        @foreach($definitions[$id] as $key => $definition)
                            @if(auth('admin')->user()->hasPermission($definition->definition['permission'] ?? 'extensions.manage'))
                                <div>
                                    <label for="{{ $id }}-{{ $key }}" class="form-label">{{ str_replace('_', ' ', ucfirst($key)) }}</label>
                                    @if($definition->definition['type'] === 'boolean')
                                        <input type="hidden" name="settings[{{ $key }}]" value="0">
                                        <input id="{{ $id }}-{{ $key }}" type="checkbox" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key, $values[$id][$key])) class="form-check-input">
                                    @else
                                        <input id="{{ $id }}-{{ $key }}" class="form-control" type="{{ $definition->secret() ? 'password' : 'text' }}" name="settings[{{ $key }}]" value="{{ $definition->secret() ? '' : old('settings.'.$key, $values[$id][$key]) }}" autocomplete="{{ $definition->secret() ? 'new-password' : 'off' }}">
                                        @if($definition->secret())<p class="small">Leave blank to keep the saved value.</p>@endif
                                    @endif
                                </div>
                            @endif
                        @endforeach
                        <button type="submit" class="btn btn-primary">Save settings</button>
                    </form>
                @endif
            @endif
        </section>
    @empty
        <p>No plugins are installed.</p>
    @endforelse
</x-admin-layout>

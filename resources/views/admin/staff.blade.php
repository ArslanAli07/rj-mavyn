<x-admin-layout title="Staff access">
<h1 class="mb-3 admin-page-title">Staff access</h1>
@if($errors->any())<p role="alert" class="text-danger mb-3">{{ $errors->first() }}</p>@endif
<p class="mb-3">Role presets include their listed permissions. Choose Custom to assign individual permissions. Use another authorized account to change your own access.</p>
@foreach($staff->concat([new \App\Models\Admin]) as $member)
<form method="POST" action="{{ $member->exists ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="bg-white border rounded-2 p-3 mb-4 admin-stack-3">
    @csrf
    @if($member->exists) @method('PUT') @endif
    <h2 class="admin-section-title">{{ $member->exists ? $member->name : 'Create staff account' }}</h2>
    <label class="d-block">Name <input class="form-control border rounded p-2" name="name" value="{{ $member->name }}" required></label>
    <label class="d-block">Email <input class="form-control border rounded p-2" name="email" type="email" value="{{ $member->email }}" required></label>
    <label class="d-block">{{ $member->exists ? 'New password (leave blank to keep)' : 'Password' }} <input class="form-control border rounded p-2" name="password" type="password" minlength="12" autocomplete="new-password" {{ $member->exists ? '' : 'required' }}></label>
    <label class="d-block">Role <select name="role" class="form-select border rounded p-2">
        @foreach($roles as $role) <option value="{{ $role }}" @selected($member->role === $role || (! in_array($member->role, $roles) && $role === 'custom'))>{{ str_replace('_', ' ', ucfirst($role)) }}</option> @endforeach
    </select></label>
    <label class="d-block">Account status <select name="is_active" class="form-select border rounded p-2"><option value="1" @selected(! $member->exists || $member->is_active)>Active</option><option value="0" @selected($member->exists && ! $member->is_active)>Inactive</option></select></label>
    <fieldset><legend class="fw-semibold">Permissions</legend><div class="d-grid gap-2 admin-cols-sm-3">
    @foreach($permissions as $permission)
        <label><input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $member->permissions ?? \App\Services\AdminPermissions::roles()[$member->role] ?? []))> {{ $permission }}</label>
    @endforeach
    </div></fieldset>
    <button class="btn btn-primary" type="submit">{{ $member->exists ? 'Save access' : 'Create account' }}</button>
</form>
@endforeach
</x-admin-layout>

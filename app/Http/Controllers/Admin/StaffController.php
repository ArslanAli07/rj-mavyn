<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index()
    {
        return view('admin.staff', ['staff' => Admin::orderBy('id')->get(), 'roles' => array_merge(['super_admin'], array_keys(AdminPermissions::roles())), 'permissions' => AdminPermissions::all()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->authorizeGrants($request, $data);
        Admin::create($data);

        return back()->with('success', 'Staff account created.');
    }

    public function update(Request $request, Admin $staff)
    {
        $data = $this->validated($request, $staff);
        $this->authorizeGrants($request, $data, $staff);
        DB::transaction(function () use ($request, $staff, $data) {
            // Serialize owner changes, preventing concurrent demotions of the last owners.
            $owners = Admin::where('role', 'super_admin')->orderBy('id')->lockForUpdate()->get();
            $current = Admin::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $this->authorizeGrants($request, $data, $current);
            $currentPermissions = $current->permissions ?? (AdminPermissions::roles()[$current->role] ?? []);
            $permissionsChanged = array_diff($data['permissions'], $currentPermissions) || array_diff($currentPermissions, $data['permissions']);
            if ($current->id === $request->user('admin')->id && ($data['role'] !== $current->role || ! $data['is_active'] || $permissionsChanged)) {
                throw ValidationException::withMessages(['role' => 'Use another authorized account to change your own access.']);
            }
            if ($current->isSuperAdmin() && $current->is_active && ($data['role'] !== 'super_admin' || ! $data['is_active']) && $owners->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active super admin.']);
            }
            $current->update($data);
        });

        return back()->with('success', 'Staff access updated.');
    }

    private function validated(Request $request, ?Admin $staff = null): array
    {
        $request->merge(['permissions' => $request->input('permissions', [])]);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('admins')->ignore($staff?->id)],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:12'],
            'role' => ['required', Rule::in(array_merge(['super_admin'], array_keys(AdminPermissions::roles())))],
            'is_active' => 'required|boolean',
            'permissions' => 'present|array',
            'permissions.*' => ['string', 'distinct', Rule::in(AdminPermissions::all())],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['permissions'] = array_values(array_unique(array_merge(AdminPermissions::roles()[$data['role']] ?? [], $data['permissions'])));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function authorizeGrants(Request $request, array $data, ?Admin $target = null): void
    {
        $actor = $request->user('admin');
        if (! $actor->isSuperAdmin()) {
            abort_if($data['role'] === 'super_admin' || $target?->isSuperAdmin(), 403);
            // Access managers cannot grant privileges they do not possess or edit stronger accounts.
            foreach (array_unique(array_merge($data['permissions'], $target?->permissions ?? (AdminPermissions::roles()[$target?->role ?? 'admin'] ?? []))) as $permission) {
                abort_unless($actor->hasPermission($permission), 403);
            }
        }
    }
}

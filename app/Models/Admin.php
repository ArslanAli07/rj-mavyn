<?php

namespace App\Models;

use App\Services\AdminPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'permissions'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'permissions' => 'array',
            'password' => 'hashed',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active || ! in_array($permission, AdminPermissions::all(), true)) {
            return false;
        }

        return $this->isSuperAdmin() || in_array($permission, $this->permissions ?? AdminPermissions::roles()[$this->role] ?? [], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}

<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (Admin::exists()) {
            return;
        }
        $name = config('platform.bootstrap_admin.name', 'Store Owner');
        $email = config('platform.bootstrap_admin.email');
        $password = config('platform.bootstrap_admin.password');
        if ($this->command && ! $this->command->option('no-interaction')) {
            $email = $email ?: $this->command->ask('Initial admin email');
            $password = $password ?: $this->command->secret('Initial admin password (at least 12 characters)');
        }
        if (! $email && ! $password) {
            $this->command?->warn('No administrator credentials configured. Set MAVYN_ADMIN_EMAIL and MAVYN_ADMIN_PASSWORD, or run admin:provision your-email@example.com.');

            return;
        }
        Validator::make(compact('name', 'email', 'password'), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:12',
        ])->validate();
        DB::transaction(function () use ($name, $email, $password) {
            $admin = Admin::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => 'super_admin', 'is_active' => true]);
            AdminAuditLog::create(['actor_id' => null, 'actor_name' => 'Database bootstrap', 'route' => 'console.db:seed',
                'action' => 'created', 'entity_type' => 'admins', 'entity_id' => (string) $admin->id,
                'changes' => ['role' => ['before' => null, 'after' => 'super_admin'], 'password' => ['before' => null, 'after' => '[redacted]']]]);
        });
        $this->command?->info('Initial administrator created.');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProvisionAdminCommand extends Command
{
    protected $signature = 'admin:provision {email} {--name=Store Owner} {--local-password-file : Generate a password in private storage for local setup}';

    protected $description = 'Create a super admin with a hidden password prompt or private local credential file';

    public function handle(): int
    {
        if ($this->option('local-password-file') && ! app()->environment('local')) {
            $this->error('Generated credential files are only available in the local environment.');

            return self::FAILURE;
        }
        $email = $this->argument('email');
        $password = $this->option('local-password-file') ? Str::password(32) : $this->secret('Password (at least 12 characters)');
        $validator = Validator::make(compact('email', 'password'), [
            'email' => 'required|email|unique:admins,email', 'password' => 'required|string|min:12',
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $path = storage_path('app/private/local-admin.txt');
        if ($this->option('local-password-file')) {
            $handle = fopen($path, 'x');
            if (! $handle) {
                $this->error('Credential file already exists or cannot be created.');

                return self::FAILURE;
            }
            chmod($path, 0600);
            fwrite($handle, "Email: {$email}\nPassword: {$password}\n");
            fclose($handle);
        }
        try {
            Admin::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'super_admin', 'is_active' => true]);
        } catch (\Throwable $exception) {
            if ($this->option('local-password-file')) {
                unlink($path);
            }
            throw $exception;
        }
        $this->info('Admin created.');
        if ($this->option('local-password-file')) {
            $this->info('Local credentials: '.$path);
        }

        return self::SUCCESS;
    }
}

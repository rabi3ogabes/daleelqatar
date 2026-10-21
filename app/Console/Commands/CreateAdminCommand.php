<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'daleel:create-admin
        {--email= : E-mail address of the administrator}
        {--name= : Display name}
        {--role=super-admin : super-admin or admin}';

    protected $description = 'Create (or promote) a Daleel Qatar administrator account. The password is always prompted, never passed as an argument.';

    public function handle(AuditLogger $audit): int
    {
        $role = (string) $this->option('role');
        if (! in_array($role, [Permissions::SUPER_ADMIN, Permissions::ADMIN], true)) {
            $this->error('Role must be super-admin or admin.');

            return self::INVALID;
        }

        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail address'))));
        $existing = User::query()->where('email', $email)->first();
        $name = (string) ($this->option('name') ?: ($existing?->name ?? $this->ask('Full name', 'Daleel Administrator')));

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email:rfc', 'max:191'],
            'name' => ['required', 'string', 'max:120'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::INVALID;
        }

        if (! $existing) {
            $password = (string) $this->secret('Password (min. 10 characters, mixed case and numbers)');
            $confirm = (string) $this->secret('Confirm password');
            $pwCheck = Validator::make(['password' => $password, 'password_confirmation' => $confirm], [
                'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
            ]);
            if ($pwCheck->fails()) {
                foreach ($pwCheck->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::INVALID;
            }
            $existing = User::query()->create(['name' => $name, 'email' => $email, 'password' => $password, 'locale' => 'ar']);
        } elseif (! $this->confirm("User {$email} exists. Grant the {$role} role?", true)) {
            return self::FAILURE;
        }

        $existing->forceFill(['email_verified_at' => $existing->email_verified_at ?? now(), 'status' => 'active'])->save();
        $existing->syncRoles([$role]);
        $audit->log('user.roles.updated', $existing, [], ['roles' => [$role]], ['via' => 'console']);

        $this->info("✔ {$email} is now a ".($role === Permissions::SUPER_ADMIN ? 'super administrator' : 'administrator').'.');

        return self::SUCCESS;
    }
}

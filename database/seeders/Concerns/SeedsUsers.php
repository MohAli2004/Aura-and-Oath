<?php

namespace Database\Seeders\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

trait SeedsUsers
{
    /**
     * `role` and `is_active` are guarded on the model, so they are applied
     * with forceFill instead of mass assignment.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertUser(string $email, array $attributes, UserRole $role, bool $isActive = true): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill($attributes);
        $user->forceFill([
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
        ])->save();

        return $user;
    }

    /**
     * Never seed a known admin password into a live site.
     */
    protected function adminSeedPassword(): string
    {
        $password = (string) config('aura.admin.password');

        if (app()->isProduction()) {
            if ($password === '' || strlen($password) < 12) {
                throw new RuntimeException(
                    'Set a strong AURA_ADMIN_PASSWORD (12+ characters) before seeding in production.'
                );
            }

            return $password;
        }

        return $password !== '' ? $password : 'password';
    }

    /**
     * Demo accounts get a throwaway password outside local development.
     */
    protected function demoUserPassword(): string
    {
        return app()->environment('local', 'testing')
            ? 'password'
            : Str::password(32);
    }

    protected function hashed(string $plain): string
    {
        return Hash::make($plain);
    }
}

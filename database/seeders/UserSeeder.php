<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('aura.admin.email')],
            [
                'name' => config('aura.admin.name'),
                'password' => Hash::make(config('aura.admin.password')),
                'role' => UserRole::Admin,
                'phone' => '+96171000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $customers = [
            ['name' => 'Nour Hassan', 'email' => 'nour@example.com', 'phone' => '+96171111111'],
            ['name' => 'Sara Ali', 'email' => 'sara@example.com', 'phone' => '+96171222222'],
            ['name' => 'Omar Farid', 'email' => 'omar@example.com', 'phone' => '+96171333333'],
            ['name' => 'Lina Khalil', 'email' => 'lina@example.com', 'phone' => '+96171444444'],
            ['name' => 'Yasmine Haddad', 'email' => 'yasmine@example.com', 'phone' => '+96171555555'],
        ];

        foreach ($customers as $c) {
            $user = User::query()->updateOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Customer,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            CustomerAddress::query()->updateOrCreate(
                ['user_id' => $user->id, 'label' => 'Home'],
                [
                    'type' => 'shipping',
                    'full_name' => $user->name,
                    'phone' => $user->phone,
                    'line1' => '12 Hamra Street',
                    'city' => 'Beirut',
                    'governorate' => 'Beirut',
                    'country' => 'LB',
                    'is_default' => true,
                ]
            );
        }
    }
}

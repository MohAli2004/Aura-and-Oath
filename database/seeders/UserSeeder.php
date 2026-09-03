<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use Database\Seeders\Concerns\SeedsUsers;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use SeedsUsers;

    public function run(): void
    {
        $this->upsertUser(
            (string) config('aura.admin.email'),
            [
                'name' => config('aura.admin.name'),
                'password' => $this->hashed($this->adminSeedPassword()),
                'phone' => '+96171000001',
                'email_verified_at' => now(),
            ],
            UserRole::Admin
        );

        $customers = [
            ['name' => 'Nour Hassan', 'email' => 'nour@example.com', 'phone' => '+96171111111'],
            ['name' => 'Sara Ali', 'email' => 'sara@example.com', 'phone' => '+96171222222'],
            ['name' => 'Omar Farid', 'email' => 'omar@example.com', 'phone' => '+96171333333'],
            ['name' => 'Lina Khalil', 'email' => 'lina@example.com', 'phone' => '+96171444444'],
            ['name' => 'Yasmine Haddad', 'email' => 'yasmine@example.com', 'phone' => '+96171555555'],
        ];

        $demoPassword = $this->hashed($this->demoUserPassword());

        foreach ($customers as $c) {
            $user = $this->upsertUser(
                $c['email'],
                [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'password' => $demoPassword,
                    'email_verified_at' => now(),
                ],
                UserRole::Customer
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

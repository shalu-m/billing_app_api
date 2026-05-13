<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => 'admin123',
                'role' => 'admin',
            ],
            [
                'name' => 'User',
                'username' => 'user',
                'email' => 'user@example.com',
                'password' => 'user123',
                'role' => 'user',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make($user['password']),
                    'role' => $user['role'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Users seeded: ' . count($users));
    }
}

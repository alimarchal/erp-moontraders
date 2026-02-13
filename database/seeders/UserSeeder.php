<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Mr. Super Admin',
                'designation' => 'Super Admin',
                'email' => 'sa@example.com',
                'is_super_admin' => 'Yes',
                'role' => 'super-admin',
            ],
            [
                'name' => 'Mr. Admin',
                'designation' => 'ADMIN',
                'email' => 'a@example.com',
                'is_super_admin' => 'No',
                'role' => 'admin',
            ],
            [
                'name' => 'Ms. User',
                'designation' => 'USER',
                'email' => 'user@example.com',
                'is_super_admin' => 'No',
                'role' => 'user',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'designation' => $userData['designation'],
                    'password' => Hash::make('password'),
                    'is_super_admin' => $userData['is_super_admin'],
                    'is_active' => 'Yes',
                ]
            );

            // Ensure role exists before assigning
            if (Role::where('name', $userData['role'])->exists()) {
                $user->syncRoles([$userData['role']]);
            }
        }
    }
}

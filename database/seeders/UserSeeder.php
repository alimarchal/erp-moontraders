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
                'password' => Hash::make('password'),
                'is_super_admin' => 'Yes',
                'is_active' => 'Yes',
                'role' => 'super-admin',
                'permissions' => [],
            ],
            [
                'name' => 'Mr. Admin',
                'designation' => 'ADMIN',
                'email' => 'a@example.com',
                'password' => Hash::make('password'),
                'is_super_admin' => 'No',
                'is_active' => 'Yes',
                'role' => 'admin',
                'permissions' => [],
            ],
            [
                'name' => 'Ms. User',
                'designation' => 'USER',
                'email' => 'user@example.com',
                'password' => Hash::make('password'),
                'is_super_admin' => 'No',
                'is_active' => 'Yes',
                'role' => 'user',
                'permissions' => [
                    'goods-receipt-note-list',
                    'goods-receipt-note-create',
                    'goods-receipt-note-edit',
                    'goods-receipt-note-post',
                    'goods-receipt-note-import',
                    'goods-receipt-note-view-own',
                    'goods-issue-list',
                    'goods-issue-create',
                    'goods-issue-edit',
                    'goods-issue-post',
                    'goods-issue-view-own',
                    'sales-settlement-list',
                    'sales-settlement-create',
                    'sales-settlement-edit',
                    'sales-settlement-post',
                    'sales-settlement-view-own',
                    'goods-issue-carton-entry',
                ],
            ],
            [
                'name' => 'WALEED',
                'designation' => 'KPO',
                'email' => 'wwaleedkhan742@gmail.com',
                'password' => '$2y$12$a3GC6FWDV3I0xTWvK2.G0ufzahqtdGE.O8TJGvIqRXidA8BcH7zsq',
                'is_super_admin' => 'No',
                'is_active' => 'Yes',
                'role' => 'user',
                'permissions' => [
                    'goods-receipt-note-list',
                    'goods-receipt-note-create',
                    'goods-receipt-note-edit',
                    'goods-receipt-note-post',
                    'goods-receipt-note-import',
                    'goods-receipt-note-view-own',
                    'goods-issue-list',
                    'goods-issue-create',
                    'goods-issue-edit',
                    'goods-issue-post',
                    'goods-issue-view-own',
                    'sales-settlement-list',
                    'sales-settlement-create',
                    'sales-settlement-post',
                    'sales-settlement-view-own',
                    'goods-issue-carton-entry',
                ],
            ],
            [
                'name' => 'FARRUKH',
                'designation' => 'KPO',
                'email' => 'farrukhshah95mzd@gmail.com',
                'password' => '$2y$12$cGO23lc0Bkqkpj8COTMgUu2xR9EqdXFBkIQV9Es1YK.E5y8WAmCxS',
                'is_super_admin' => 'No',
                'is_active' => 'Yes',
                'role' => 'user',
                'permissions' => [
                    'goods-receipt-note-list',
                    'goods-receipt-note-create',
                    'goods-receipt-note-edit',
                    'goods-receipt-note-post',
                    'goods-receipt-note-import',
                    'goods-receipt-note-view-own',
                    'goods-issue-list',
                    'goods-issue-create',
                    'goods-issue-edit',
                    'goods-issue-post',
                    'goods-issue-view-own',
                    'sales-settlement-list',
                    'sales-settlement-create',
                    'sales-settlement-edit',
                    'sales-settlement-post',
                    'sales-settlement-view-own',
                    'inventory-view',
                    'goods-issue-carton-entry',
                ],
            ],
            [
                'name' => 'ABDULLAH',
                'designation' => 'KPO',
                'email' => 'workflow317@gmail.com',
                'password' => '$2y$12$dKOR50PwtcfF.LiR/x0SpOhuTHg8lKNa4wiFCFx97MYvjJNnIhPC6',
                'is_super_admin' => 'No',
                'is_active' => 'Yes',
                'role' => 'user',
                'permissions' => [
                    'goods-receipt-note-list',
                    'goods-receipt-note-create',
                    'goods-receipt-note-edit',
                    'goods-receipt-note-post',
                    'goods-receipt-note-import',
                    'goods-receipt-note-view-own',
                    'goods-issue-list',
                    'goods-issue-create',
                    'goods-issue-edit',
                    'goods-issue-post',
                    'goods-issue-view-own',
                    'sales-settlement-list',
                    'sales-settlement-create',
                    'sales-settlement-edit',
                    'sales-settlement-post',
                    'sales-settlement-view-own',
                    'goods-issue-carton-entry',
                ],
            ],
            [
                'name' => 'AKASH',
                'designation' => 'KPO',
                'email' => 'akashsheikhkashi925@gmail.com',
                'password' => '$2y$12$v9I/D7Ia3Pbv5.Ke6qRaluhRI9yq0n12nOpMpCaUUEYQwSh6MI3Xe',
                'is_super_admin' => 'No',
                'is_active' => 'Yes',
                'role' => 'user',
                'permissions' => [
                    'goods-receipt-note-list',
                    'goods-receipt-note-create',
                    'goods-receipt-note-edit',
                    'goods-receipt-note-post',
                    'goods-receipt-note-import',
                    'goods-receipt-note-view-own',
                    'goods-issue-list',
                    'goods-issue-create',
                    'goods-issue-edit',
                    'goods-issue-post',
                    'goods-issue-view-own',
                    'sales-settlement-list',
                    'sales-settlement-create',
                    'sales-settlement-edit',
                    'sales-settlement-post',
                    'sales-settlement-view-own',
                    'goods-issue-carton-entry',
                ],
            ],
            [
                'name' => 'Waris Shah',
                'designation' => 'KPO',
                'email' => '69xshah@gmail.com',
                'password' => '$2y$12$dXC5LEtYF/q9pEQ4CEjA0uM8jEh4n2e/BgwSprnz7Bv9AgknPNsHy',
                'is_super_admin' => 'No',
                'is_active' => 'Yes',
                'role' => 'user',
                'permissions' => [
                    'goods-receipt-note-list',
                    'goods-receipt-note-create',
                    'goods-receipt-note-edit',
                    'goods-receipt-note-post',
                    'goods-receipt-note-import',
                    'goods-receipt-note-view-own',
                    'goods-issue-list',
                    'goods-issue-create',
                    'goods-issue-edit',
                    'goods-issue-post',
                    'goods-issue-view-own',
                    'sales-settlement-list',
                    'sales-settlement-create',
                    'sales-settlement-edit',
                    'sales-settlement-post',
                    'sales-settlement-view-own',
                    'inventory-view',
                    'goods-issue-carton-entry',
                ],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'designation' => $userData['designation'],
                    'password' => $userData['password'],
                    'is_super_admin' => $userData['is_super_admin'],
                    'is_active' => $userData['is_active'],
                ]
            );

            if (Role::where('name', $userData['role'])->exists()) {
                $user->syncRoles([$userData['role']]);
            }

            if (! empty($userData['permissions'])) {
                $user->syncPermissions($userData['permissions']);
            }
        }
    }
}

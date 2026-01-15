<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('role-list');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('role-list');
    }

    public function create(User $user): bool
    {
        return $user->can('role-create');
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->name === 'super-admin' && $user->is_super_admin !== 'Yes') {
            return false;
        }

        return $user->can('role-edit');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->name === 'super-admin') {
            return false;
        }

        return $user->can('role-delete');
    }
}

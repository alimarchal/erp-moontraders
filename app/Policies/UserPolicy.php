<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user-list');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('user-list');
    }

    public function create(User $user): bool
    {
        return $user->can('user-create');
    }

    public function update(User $user, User $model): bool
    {
        // Prevent editing super-admin unless user is super-admin
        if ($model->is_super_admin === 'Yes' && $user->is_super_admin !== 'Yes') {
            return false;
        }

        return $user->can('user-edit');
    }

    public function delete(User $user, User $model): bool
    {
        // Cannot delete self or super-admin
        if ($model->id === $user->id || $model->is_super_admin === 'Yes') {
            return false;
        }

        return $user->can('user-delete');
    }
}

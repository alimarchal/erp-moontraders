<?php

namespace App\Policies;

use App\Models\LegerRegister;
use App\Models\User;

class LegerRegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('report-audit-leger-register');
    }

    public function view(User $user, LegerRegister $legerRegister): bool
    {
        return $user->can('report-audit-leger-register');
    }

    public function create(User $user): bool
    {
        return $user->can('report-audit-leger-register-manage');
    }

    public function update(User $user, LegerRegister $legerRegister): bool
    {
        return $user->can('report-audit-leger-register-manage');
    }

    public function delete(User $user, LegerRegister $legerRegister): bool
    {
        return $user->can('report-audit-leger-register-manage');
    }

    public function restore(User $user, LegerRegister $legerRegister): bool
    {
        return $user->can('report-audit-leger-register-manage');
    }

    public function forceDelete(User $user, LegerRegister $legerRegister): bool
    {
        return $user->can('report-audit-leger-register-manage');
    }
}

<?php

namespace App\Policies;

use App\Models\LedgerRegister;
use App\Models\User;

class LedgerRegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('report-audit-ledger-register');
    }

    public function view(User $user, LedgerRegister $ledgerRegister): bool
    {
        return $user->can('report-audit-ledger-register');
    }

    public function create(User $user): bool
    {
        return $user->can('report-audit-ledger-register-manage');
    }

    public function update(User $user, LedgerRegister $ledgerRegister): bool
    {
        return $user->can('report-audit-ledger-register-manage');
    }

    public function delete(User $user, LedgerRegister $ledgerRegister): bool
    {
        return $user->can('report-audit-ledger-register-manage');
    }

    public function restore(User $user, LedgerRegister $ledgerRegister): bool
    {
        return $user->can('report-audit-ledger-register-manage');
    }

    public function forceDelete(User $user, LedgerRegister $ledgerRegister): bool
    {
        return $user->can('report-audit-ledger-register-manage');
    }
}

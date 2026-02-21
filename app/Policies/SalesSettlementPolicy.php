<?php

namespace App\Policies;

use App\Models\SalesSettlement;
use App\Models\User;

class SalesSettlementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('sales-settlement-list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SalesSettlement $settlement): bool
    {
        if (! $user->can('sales-settlement-list')) {
            return false;
        }

        return $this->hasOwnership($user, $settlement);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('sales-settlement-create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SalesSettlement $settlement): bool
    {
        if ($settlement->status === 'posted') {
            return false;
        }

        if (! $user->can('sales-settlement-edit')) {
            return false;
        }

        return $this->hasOwnership($user, $settlement);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SalesSettlement $settlement): bool
    {
        if ($settlement->status === 'posted') {
            return false;
        }

        if (! $user->can('sales-settlement-delete')) {
            return false;
        }

        return $this->hasOwnership($user, $settlement);
    }

    /**
     * Determine whether the user can post the model.
     */
    public function post(User $user, SalesSettlement $settlement): bool
    {
        if ($settlement->status === 'posted') {
            return false;
        }

        if (! $user->can('sales-settlement-post')) {
            return false;
        }

        return $this->hasOwnership($user, $settlement);
    }

    /**
     * Check if the user owns the settlement or has "view-all" permission.
     */
    private function hasOwnership(User $user, SalesSettlement $settlement): bool
    {
        if ($user->can('sales-settlement-view-all')) {
            return true;
        }

        return $settlement->created_by === $user->id;
    }
}

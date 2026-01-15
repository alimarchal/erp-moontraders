<?php

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;

class SupplierPaymentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('supplier-payment-list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SupplierPayment $payment): bool
    {
        return $user->can('supplier-payment-list');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('supplier-payment-create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupplierPayment $payment): bool
    {
        if ($payment->status === 'posted') {
            return false;
        }

        return $user->can('supplier-payment-edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupplierPayment $payment): bool
    {
        if ($payment->status === 'posted') {
            return false;
        }

        return $user->can('supplier-payment-delete');
    }

    /**
     * Determine whether the user can post the model.
     */
    public function post(User $user, SupplierPayment $payment): bool
    {
        if ($payment->status === 'posted') {
            return false;
        }

        return $user->can('supplier-payment-post');
    }

    /**
     * Determine whether the user can reverse the model.
     */
    public function reverse(User $user, SupplierPayment $payment): bool
    {
        if ($payment->status !== 'posted') {
            return false;
        }

        return $user->can('supplier-payment-reverse');
    }
}

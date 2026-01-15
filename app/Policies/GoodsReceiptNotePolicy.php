<?php

namespace App\Policies;

use App\Models\GoodsReceiptNote;
use App\Models\User;

class GoodsReceiptNotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('goods-receipt-note-list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GoodsReceiptNote $goodsReceiptNote): bool
    {
        return $user->can('goods-receipt-note-list');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('goods-receipt-note-create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GoodsReceiptNote $goodsReceiptNote): bool
    {
        // Typically cannot edit posted GRNs
        if ($goodsReceiptNote->status === 'posted') {
            return false;
        }

        return $user->can('goods-receipt-note-edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GoodsReceiptNote $goodsReceiptNote): bool
    {
        // Typically cannot delete posted GRNs
        if ($goodsReceiptNote->status === 'posted') {
            return false;
        }

        return $user->can('goods-receipt-note-delete');
    }

    /**
     * Determine whether the user can post the model.
     */
    public function post(User $user, GoodsReceiptNote $goodsReceiptNote): bool
    {
        if ($goodsReceiptNote->status === 'posted') {
            return false;
        }

        return $user->can('goods-receipt-note-post');
    }

    /**
     * Determine whether the user can reverse the model.
     */
    public function reverse(User $user, GoodsReceiptNote $goodsReceiptNote): bool
    {
        if ($goodsReceiptNote->status !== 'posted') {
            return false;
        }

        return $user->can('goods-receipt-note-reverse');
    }
}

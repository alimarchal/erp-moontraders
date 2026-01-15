<?php

namespace App\Policies;

use App\Models\GoodsIssue;
use App\Models\User;

class GoodsIssuePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('goods-issue-list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GoodsIssue $gi): bool
    {
        return $user->can('goods-issue-list');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('goods-issue-create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GoodsIssue $gi): bool
    {
        if ($gi->status === 'posted') {
            return false;
        }

        return $user->can('goods-issue-edit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GoodsIssue $gi): bool
    {
        if ($gi->status === 'posted') {
            return false;
        }

        return $user->can('goods-issue-delete');
    }

    /**
     * Determine whether the user can post the model.
     */
    public function post(User $user, GoodsIssue $gi): bool
    {
        if ($gi->status === 'posted') {
            return false;
        }

        return $user->can('goods-issue-post');
    }
}

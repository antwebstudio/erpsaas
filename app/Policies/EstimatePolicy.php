<?php

namespace App\Policies;

use App\Models\Accounting\Estimate;
use App\Models\User;

class EstimatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_sales::estimate')
            || $user->can('view_mine_sales::estimate');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Estimate $model): bool
    {
        if ($user->can('view_any_sales::estimate') || $user->can('view_sales::estimate')) {
            return true;
        }

        return $user->can('view_mine_sales::estimate') && $this->isOwnedByUser($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_sales::estimate');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Estimate $estimate): bool
    {
        if ($estimate->wasConverted()) {
            return false;
        }

        if ($user->can('update_any_sales::estimate')) {
            return true;
        }

        return $user->can('update_sales::estimate') && $this->isOwnedByUser($user, $estimate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Estimate $model): bool
    {
        return $user->can('delete_sales::estimate');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Estimate $model): bool
    {
        return $user->can('restore_sales::estimate');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Estimate $model): bool
    {
        return $user->can('force_delete_sales::estimate');
    }

    /**
     * Check if the user owns the estimate (created the estimate or created the associated client).
     */
    protected function isOwnedByUser(User $user, Estimate $estimate): bool
    {
        if ($estimate->created_by === $user->id) {
            return true;
        }

        if ($estimate->client_id && $estimate->clientAndLead?->created_by === $user->id) {
            return true;
        }

        return false;
    }
}

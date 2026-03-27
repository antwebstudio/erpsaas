<?php

namespace App\Policies;

use App\Enums\Accounting\AdjustmentStatus;
use App\Models\Accounting\Adjustment;
use App\Models\User;

class AdjustmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_adjustment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Adjustment $model): bool
    {
        return $user->can('view_adjustment');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_adjustment');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Adjustment $adjustment): bool
    {
        if ($adjustment->status === AdjustmentStatus::Archived) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Adjustment $model): bool
    {
        return $user->can('delete_adjustment');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Adjustment $model): bool
    {
        return $user->can('restore_adjustment');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Adjustment $model): bool
    {
        return $user->can('force_delete_adjustment');
    }
}

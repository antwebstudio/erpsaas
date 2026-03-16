<?php

namespace App\Policies;

use App\Models\Common\Vendor;
use App\Models\User;

class VendorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_purchases::vendor');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vendor $model): bool
    {
        return $user->can('view_purchases::vendor');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_purchases::vendor');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vendor $model): bool
    {
        return $user->can('update_purchases::vendor');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vendor $model): bool
    {
        return $user->can('delete_purchases::vendor');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vendor $model): bool
    {
        return $user->can('restore_purchases::vendor');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Vendor $model): bool
    {
        return $user->can('force_delete_purchases::vendor');
    }
}

<?php

namespace App\Policies;

use App\Models\Common\AllClient;
use App\Models\User;

class AllClientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_sales::all::client');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AllClient $model): bool
    {
        return $user->can('view_sales::all::client');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_sales::all::client');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AllClient $model): bool
    {
        return $user->can('update_sales::all::client');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AllClient $model): bool
    {
        return $user->can('delete_sales::all::client');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AllClient $model): bool
    {
        return $user->can('restore_sales::all::client');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AllClient $model): bool
    {
        return $user->can('force_delete_sales::all::client');
    }
}

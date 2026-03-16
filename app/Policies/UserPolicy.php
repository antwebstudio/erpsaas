<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the core::user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_core::user');
    }

    /**
     * Determine whether the core::user can view the model.
     */
    public function view(User $user, $model): bool
    {
        return $user->can('view_core::user');
    }

    /**
     * Determine whether the core::user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_core::user');
    }

    /**
     * Determine whether the core::user can update the model.
     */
    public function update(User $user, $model): bool
    {
        return $user->can('update_core::user');
    }

    /**
     * Determine whether the core::user can delete the model.
     */
    public function delete(User $user, $model): bool
    {
        return $user->can('delete_core::user');
    }

    /**
     * Determine whether the core::user can restore the model.
     */
    public function restore(User $user, $model): bool
    {
        return $user->can('restore_core::user');
    }

    /**
     * Determine whether the core::user can permanently delete the model.
     */
    public function forceDelete(User $user, $model): bool
    {
        return $user->can('force_delete_core::user');
    }
}
<?php

namespace App\Policies;

use App\Models\User;

class OfferingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_common::offering') || $user->can('view_any_common::job::scope::option');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $model): bool
    {
        return $user->can('view_common::offering') || $user->can('view_common::job::scope::option');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_common::offering') || $user->can('create_common::job::scope::option');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $model): bool
    {
        return $user->can('update_common::offering') || $user->can('update_common::job::scope::option');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, $model): bool
    {
        return $user->can('delete_common::offering') || $user->can('delete_common::job::scope::option');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, $model): bool
    {
        return $user->can('restore_common::offering') || $user->can('restore_common::job::scope::option');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, $model): bool
    {
        return $user->can('force_delete_common::offering') || $user->can('force_delete_common::job::scope::option');
    }
}

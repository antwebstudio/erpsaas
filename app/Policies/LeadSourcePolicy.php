<?php

namespace App\Policies;

use App\Models\Common\LeadSource;
use App\Models\User;

class LeadSourcePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_sales::lead::source');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LeadSource $model): bool
    {
        return $user->can('view_sales::lead::source');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_sales::lead::source');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LeadSource $model): bool
    {
        return $user->can('update_sales::lead::source');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LeadSource $model): bool
    {
        return $user->can('delete_sales::lead::source');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LeadSource $model): bool
    {
        return $user->can('restore_sales::lead::source');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LeadSource $model): bool
    {
        return $user->can('force_delete_sales::lead::source');
    }
}

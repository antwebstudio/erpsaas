<?php

namespace App\Policies;

use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_sales::lead') || $user->can('view_mine_sales::lead');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $model): bool
    {
        if ($user->can('view_sales::lead')) {
            return true;
        }

        if ($user->can('view_mine_sales::lead') && $model->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_sales::lead');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $model): bool
    {
        return $user->can('update_sales::lead');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, $model): bool
    {
        return $user->can('delete_sales::lead');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, $model): bool
    {
        return $user->can('restore_sales::lead');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, $model): bool
    {
        return $user->can('force_delete_sales::lead');
    }

    public function assignLead(User $user): bool
    {
        return $user->can('assign_lead_sales::lead');
    }

    public function archive(User $user, $model): bool
    {
        return $user->can('archive_sales::lead');
    }
}

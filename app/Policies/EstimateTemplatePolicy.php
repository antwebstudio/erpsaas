<?php

namespace App\Policies;

use App\Models\Accounting\EstimateTemplate;
use App\Models\User;

class EstimateTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_sales::estimate::template');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EstimateTemplate $model): bool
    {
        return $user->can('view_sales::estimate::template');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_sales::estimate::template');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EstimateTemplate $model): bool
    {
        return $user->can('update_sales::estimate::template');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EstimateTemplate $model): bool
    {
        return $user->can('delete_sales::estimate::template');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EstimateTemplate $model): bool
    {
        return $user->can('restore_sales::estimate::template');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EstimateTemplate $model): bool
    {
        return $user->can('force_delete_sales::estimate::template');
    }
}

<?php

namespace App\Policies;

use App\Models\Accounting\VariationOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class VariationOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_sales::variation::order')
            || $user->can('view_mine_sales::variation::order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VariationOrder $variationOrder): bool
    {
        if ($user->can('view_any_sales::variation::order') || $user->can('view_sales::variation::order')) {
            return true;
        }

        return $user->can('view_mine_sales::variation::order') && $variationOrder->created_by === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_sales::variation::order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VariationOrder $variationOrder): bool
    {
        if ($user->can('update_any_sales::variation::order')) {
            return true;
        }

        return $user->can('update_sales::variation::order') && $variationOrder->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VariationOrder $variationOrder): bool
    {
        return $user->can('delete_sales::variation::order');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VariationOrder $variationOrder): bool
    {
        return $user->can('restore_sales::variation::order');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VariationOrder $variationOrder): bool
    {
        return $user->can('force_delete_sales::variation::order');
    }
}

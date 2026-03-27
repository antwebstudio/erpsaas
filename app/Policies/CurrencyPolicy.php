<?php

namespace App\Policies;

use App\Models\Setting\Currency;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CurrencyPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_setting::currency');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Currency $model): bool
    {
        return $user->can('view_setting::currency');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_setting::currency');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Currency $currency): bool
    {
        return $currency->isDisabled();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Currency $model): bool
    {
        return $user->can('delete_setting::currency');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Currency $model): bool
    {
        return $user->can('restore_setting::currency');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Currency $model): bool
    {
        return $user->can('force_delete_setting::currency');
    }
}

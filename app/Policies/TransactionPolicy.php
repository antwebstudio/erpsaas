<?php

namespace App\Policies;

use App\Models\Accounting\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_transaction');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Transaction $model): bool
    {
        return $user->can('view_transaction');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_transaction');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        if ($transaction->transactionable_id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Transaction $model): bool
    {
        return $user->can('delete_transaction');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Transaction $model): bool
    {
        return $user->can('restore_transaction');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Transaction $model): bool
    {
        return $user->can('force_delete_transaction');
    }
}

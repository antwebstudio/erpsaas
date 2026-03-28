<?php

namespace App\Policies;

use App\Models\Setting\DocumentDefault;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentDefaultPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_document::default');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentDefault $documentDefault): bool
    {
        return $user->can('view_document::default');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_document::default');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentDefault $documentDefault): bool
    {
        return $user->can('update_document::default');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentDefault $documentDefault): bool
    {
        return $user->can('delete_document::default');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocumentDefault $documentDefault): bool
    {
        return $user->can('restore_document::default');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocumentDefault $documentDefault): bool
    {
        return $user->can('force_delete_document::default');
    }
}

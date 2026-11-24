<?php

namespace App\Filament\Traits;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

/**
 * Adds a "view_index" Shield permission that controls access to a resource's
 * list (index) page only. Kept separate from view_any/view_mine because
 * Filament requires canViewAny() for every resource page (view/edit/create).
 *
 * The using class must implement HasShieldPermissions and define
 * VIEW_INDEX_PERMISSION, e.g. 'view_index_sales::estimate'.
 */
trait HasViewIndexPermission
{
    public static function getPermissionPrefixes(): array
    {
        return array_values(array_unique([
            ...config('filament-shield.permission_prefixes.resource', []),
            'view_index',
        ]));
    }

    public static function canViewIndex(): bool
    {
        $user = Auth::user();

        return $user !== null
            && static::canViewAny()
            && $user->can(static::VIEW_INDEX_PERMISSION);
    }

    /**
     * Index URL when the user may see the list, otherwise the panel home.
     */
    public static function getIndexOrFallbackUrl(): string
    {
        return static::canViewIndex()
            ? static::getUrl('index')
            : Filament::getUrl();
    }
}

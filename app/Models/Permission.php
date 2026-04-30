<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        // CurrentCompanyScope on Role model would filter by session company, breaking
        // cross-company permission checks. The role_has_permissions pivot has no team
        // column, so Spatie intends all matching roles to be returned here.
        return parent::roles()->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
    }
}

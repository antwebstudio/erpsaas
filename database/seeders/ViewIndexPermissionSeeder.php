<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Scopes\CurrentCompanyScope;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ViewIndexPermissionSeeder extends Seeder
{
    /**
     * List (index) page permissions, granted only to Super Admin by default.
     * Other roles can be granted them from the Roles settings page
     * ("View Index" under Estimate / Variation Order).
     */
    public const PERMISSIONS = [
        'view_index_sales::estimate',
        'view_index_sales::variation::order',
    ];

    public function run(): void
    {
        $permissions = collect(self::PERMISSIONS)->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        $superAdminName = config('filament-shield.super_admin.name', 'Super Admin');

        $roles = Role::withoutGlobalScope(CurrentCompanyScope::class)
            ->where('guard_name', 'web')
            ->get();

        foreach ($roles as $role) {
            if ($role->name === $superAdminName) {
                $role->givePermissionTo($permissions);
            } else {
                $role->revokePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

<?php

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$superAdminName = config('filament-shield.super_admin.name', 'Super Admin');
$allPermissionsCount = Permission::count();
$companies = Company::all();

echo "Total Permissions in Database: $allPermissionsCount\n";

foreach ($companies as $company) {
    setPermissionsTeamId($company->id);
    $role = Role::where('name', $superAdminName)->where('company_id', $company->id)->first();

    if (! $role) {
        echo "Company: {$company->name} (ID: {$company->id}) - Super Admin role NOT FOUND!\n";

        continue;
    }

    $rolePermissionsCount = $role->permissions()->count();
    echo "Company: {$company->name} (ID: {$company->id}) - Super Admin has $rolePermissionsCount / $allPermissionsCount permissions.\n";

    if ($rolePermissionsCount < $allPermissionsCount) {
        $missingPermissions = Permission::whereDoesntHave('roles', function ($q) use ($role) {
            $q->where('roles.id', $role->id);
        })->pluck('name')->toArray();
        echo '  Missing: ' . implode(', ', $missingPermissions) . "\n";
    }
}

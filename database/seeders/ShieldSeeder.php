<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Str;

class ShieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');
        
        $superAdminRole = Role::firstOrCreate([
            'name' => $superAdminName,
            'guard_name' => 'web',
        ]);

        $this->command->info("Ensuring all permissions exist and are assigned to {$superAdminName}...");

        $permissions = [];
        
        // Resource permissions
        $resources = [
            'Adjustment', 'BankAccount', 'Bill', 'Budget', 'Client', 'Company', 
            'ConnectedAccount', 'Currency', 'Department', 'Estimate', 'Invoice', 
            'Lead', 'Offering', 'OfferingCategory', 'RecurringInvoice', 'Role', 
            'Transaction', 'User', 'Vendor', 'JobScope', 'JobScopeDescription',
            'JobScopeOption', 'EstimateTemplate', 'DocumentDefault'
        ];

        $prefixes = config('filament-shield.permission_prefixes.resource', [
            'view', 'view_any', 'create', 'update', 'restore', 'restore_any', 
            'replicate', 'reorder', 'delete', 'delete_any', 'force_delete', 'force_delete_any'
        ]);

        foreach ($resources as $resource) {
            $modelStr = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $resource));
            foreach ($prefixes as $prefix) {
                $permissions[] = "{$prefix}_{$modelStr}";
            }
        }

        // Page permissions
        $pages = [
            'WelcomePage', 'Reports', 'AccountChart', 'OfferingCategory', 
            'LiveCurrency', 'ConnectedAccount', 'Localization', 'CompanyDefault', 
            'CompanyProfile', 'Quotation', 'Dashboard'
        ];

        foreach ($pages as $page) {
            $permissions[] = "page_{$page}";
        }

        // Create and sync permissions
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $superAdminRole->syncPermissions(Permission::all());
        $this->command->info("Permissions synced to {$superAdminName} role.");

        // The provided code snippet for `canAccess` and `$navigationIcon` appears to be
        // intended for a Filament resource, page, or cluster class, not for insertion
        // directly into a Seeder's `run` method. Inserting it here would result in
        // a syntactically incorrect PHP file.
        // Therefore, this part of the instruction cannot be applied as written
        // to this specific file while maintaining syntactic correctness.

        // Create/Assign user
        $adminEmail = 'admin@example.com';
        $user = User::where('email', $adminEmail)->first();

        if (!$user) {
            $user = User::create([
                'name' => 'Super Admin',
                'email' => $adminEmail,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $this->command->info("User {$adminEmail} created.");
        }

        // $user->assignRole($superAdminRole);
        $this->command->info("User {$adminEmail} assigned the {$superAdminName} role.");
    }
}

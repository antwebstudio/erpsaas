<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Company;
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
        $superAdminName = config('filament-shield.super_admin.name', 'Super Admin');
        
        $this->command->info("Ensuring all permissions exist...");

        $permissions = [];
        
        // Resource permissions
        $resourceMap = [
            'Adjustment' => 'accounting::adjustment',
            'BankAccount' => 'banking::account',
            'Bill' => 'purchases::bill',
            'Budget' => 'accounting::budget',
            'Client' => 'sales::client',
            'Company' => 'core::company',
            'ConnectedAccount' => 'core::connected_account',
            'Currency' => 'settings::currency',
            'Department' => 'core::department',
            'Estimate' => 'sales::estimate',
            'Invoice' => 'sales::invoice',
            'Lead' => 'sales::lead',
            'Offering' => 'common::offering',
            'OfferingCategory' => 'common::offering::category',
            'RecurringInvoice' => 'sales::recurring_invoice',
            'Role' => 'shield::role',
            'Transaction' => 'accounting::transaction',
            'User' => 'core::user',
            'Vendor' => 'purchases::vendor',
            'JobScope' => 'common::job::scope',
            'JobScopeDescription' => 'common::job::scope::description',
            'JobScopeOption' => 'common::job::scope::option',
            'EstimateTemplate' => 'sales::estimate::template',
            'DocumentDefault' => 'settings::document_default',
            'Contract' => 'sales::contract',
            'VariationOrder' => 'sales::variation_order',
        ];

        $prefixes = config('filament-shield.permission_prefixes.resource', [
            'view', 'view_any', 'create', 'update', 'restore', 'restore_any', 
            'replicate', 'reorder', 'delete', 'delete_any', 'force_delete', 'force_delete_any'
        ]);

        foreach ($resourceMap as $model => $identifier) {
            foreach ($prefixes as $prefix) {
                $permissions[] = "{$prefix}_{$identifier}";
            }
        }

        // Page permissions
        $pageMap = [
            'WelcomePage' => 'page_WelcomePage',
            'Reports' => 'page_Reports',
            'AccountChart' => 'page_AccountChart',
            'Localization' => 'page_Localization',
            'CompanyProfile' => 'page_CompanyProfile',
            'Dashboard' => 'page_Dashboard',
            'ConnectedAccount' => 'page_ConnectedAccount',
            'LiveCurrency' => 'page_LiveCurrency',
        ];

        foreach ($pageMap as $page => $permission) {
            $permissions[] = $permission;
        }

        // Create permissions
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $allPermissions = Permission::all();
        $companies = Company::all();

        foreach ($companies as $company) {
            $role = Role::firstOrCreate([
                'name' => $superAdminName,
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);

            $role->syncPermissions($allPermissions);
            $this->command->info("Super Admin role and permissions synced for company: {$company->name}");
        }

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

        $firstCompany = $companies->first();
        if ($firstCompany) {
            $user->switchCompany($firstCompany);
        }

        foreach ($companies as $company) {
            if (!$user->belongsToCompany($company)) {
                $user->companies()->attach($company, ['role' => 'admin']);
            }

            $user->assignRolesForCompany($company->id, $superAdminName);
            $this->command->info("User {$adminEmail} assigned the {$superAdminName} role in company: {$company->name}");
        }
    }
}

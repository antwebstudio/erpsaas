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
    protected $adminEmail = [
        2 => "muyi@example.com",
        3 => "designstudio@example.com",
        4 => "stylemyspace@example.com",
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminName = config('filament-shield.super_admin.name', 'Super Admin');
        
        $this->command->info("Ensuring all permissions exist...");

        $permissions = [];
        
        // Resource permissions
        $companyResource = [
            'Adjustment' => 'adjustment',
            'BankAccount' => 'banking::account',
            'Bill' => 'purchases::bill',
            'Budget' => 'accounting::budget',
            'Client' => 'sales::client',
            'Company' => 'core::company',
            'ConnectedAccount' => 'core::connected_account',
            'Currency' => 'currency',
            'Department' => 'core::department',
            'Invoice' => 'sales::invoice',
            'Offering' => 'common::offering',
            'RecurringInvoice' => 'sales::recurring::invoice',
            'Role' => 'role',
            'Transaction' => 'accounting::transaction',
            'Vendor' => 'purchases::vendor',
            'DocumentDefault' => 'document::default',
        ];

        $globalResource = [
            'User' => 'core::user',
            'Lead' => 'sales::lead',
            'EstimateTemplate' => 'sales::estimate::template',
            'Estimate' => 'sales::estimate',
            'Contract' => 'sales::contract',
            'OfferingCategory' => 'common::offering::category',
            'VariationOrder' => 'sales::variation::order',
            'JobScope' => 'common::job::scope',
            'JobScopeDescription' => 'common::job::scope::description',
            'JobScopeOption' => 'common::job::scope::option',
            'AllClient' => 'sales::all::client',
        ];

        $prefixes = config('filament-shield.permission_prefixes.resource', [
            'view', 'view_any', 'create', 'update', 'restore', 'restore_any', 
            'replicate', 'reorder', 'delete', 'delete_any', 'force_delete', 'force_delete_any'
        ]);

        $companyResourcePermissions = [];
        foreach ($companyResource as $model => $identifier) {
            foreach ($prefixes as $prefix) {
                $companyResourcePermissions[] = "{$prefix}_{$identifier}";
                $permissions[] = "{$prefix}_{$identifier}";
            }
        }

        $globalResourcePermissions = [];
        foreach ($globalResource as $model => $identifier) {
            foreach ($prefixes as $prefix) {
                $globalResourcePermissions[] = "{$prefix}_{$identifier}";
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

            // "page_OfferingCategory",
            // "page_AccountBalances",
            // "page_AccountTransactions",
            // "page_AccountsPayableAging",
            // "page_AccountsReceivableAging",
            // "page_BalanceSheet",
            // "page_CashFlowStatement",
            // "page_ClientBalanceSummary",
            // "page_ClientPaymentPerformance",
            // "page_IncomeStatement",
            // "page_TrialBalance",
            // "page_VendorBalanceSummary",
            // "page_VendorPaymentPerformance",
            // "page_CompanyDefault",
            // "page_Quotation",
            // "page_CreateQuotation",
            // "page_PersonalAccessTokens",
            // "page_Profile",
            // "page_ManageCompany",
        ];

        $pagePermissions = [];
        foreach ($pageMap as $page => $permission) {
            $pagePermissions[] = $permission;
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

            $adminRole = Role::firstOrCreate([
                'name' => 'Admin',
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);

            if ($company->id == 1) {
                $adminPermissions = array_merge($pagePermissions, $globalResourcePermissions);
            } else {
                $adminPermissions = array_merge($pagePermissions, $companyResourcePermissions);
            }

            $adminRole->syncPermissions($adminPermissions);
            $this->command->info("Admin role and permissions synced for company: {$company->name}");
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
                'current_company_id' => 1,  // Assuming this will be the ID of the created company
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

        // Assign Admin role to admin@erpsaas.com for all companies
        $erpsaasAdminEmail = 'admin@erpsaas.com';
        $erpsaasAdmin = User::where('email', $erpsaasAdminEmail)->first();
        
        if (!$erpsaasAdmin) {
            $erpsaasAdmin = User::create([
                'name' => 'Admin',
                'email' => $erpsaasAdminEmail,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'current_company_id' => 1,
            ]);
            $this->command->info("User {$erpsaasAdminEmail} created.");
        }

        if ($firstCompany && !$erpsaasAdmin->current_company_id) {
            $erpsaasAdmin->switchCompany($firstCompany);
        }

        foreach ($companies as $company) {
            if (!$erpsaasAdmin->belongsToCompany($company)) {
                $erpsaasAdmin->companies()->attach($company, ['role' => 'admin']);
            }

            $erpsaasAdmin->assignRolesForCompany($company->id, 'Admin');
            $this->command->info("User {$erpsaasAdminEmail} assigned the Admin role in company: {$company->name}");
        }

        // Create an individual Admin for each company
        foreach ($companies as $company) {
            $companyAdminEmail = $this->adminEmail[$company->id] ?? "admin{$company->id}@example.com";
            $companyAdmin = User::where('email', $companyAdminEmail)->first();
            
            if (!$companyAdmin) {
                $companyAdmin = User::create([
                    'name' => "Admin " . $company->name,
                    'email' => $companyAdminEmail,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'current_company_id' => $company->id,
                ]);
                $this->command->info("User {$companyAdminEmail} created.");
            }

            if (!$companyAdmin->current_company_id) {
                $companyAdmin->switchCompany($company);
            }

            if (!$companyAdmin->belongsToCompany($company)) {
                $companyAdmin->companies()->attach($company, ['role' => 'admin']);
            }

            $companyAdmin->assignRolesForCompany($company->id, 'Admin');
            $this->command->info("User {$companyAdminEmail} assigned the Admin role exclusively for company: {$company->name}");
        }
    }
}

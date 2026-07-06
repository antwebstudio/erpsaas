<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShieldSeeder extends Seeder
{


    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminName = config('filament-shield.super_admin.name', 'Super Admin');

        $this->command->info('Ensuring all permissions exist...');

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
            'LeadSource' => 'sales::lead::source',
            'Offering' => 'common::offering',
            'RecurringInvoice' => 'sales::recurring::invoice',
            'Role' => 'role',
            'Transaction' => 'accounting::transaction',
            'Vendor' => 'purchases::vendor',
            'DocumentDefault' => 'document::default',
            'MailLog' => 'mail::mail::log',
            'MailTemplate' => 'mail::mail::template',
            'MailSuppression' => 'mail::mail::suppression',
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
            'view', 'view_any', 'view_mine', 'create', 'update', 'update_any', 'restore', 'restore_any',
            'replicate', 'reorder', 'delete', 'delete_any', 'force_delete', 'force_delete_any',
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
            'CompanyDefault' => 'page_CompanyDefault',
            'Quotation' => 'page_Quotation',
            'Dashboard' => 'page_Dashboard',
            'ConnectedAccount' => 'page_ConnectedAccount',
            'LiveCurrency' => 'page_LiveCurrency',
            'ManageCompany' => 'page_ManageCompany',

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
            // "page_CreateQuotation",
            // "page_PersonalAccessTokens",
            // "page_Profile",
        ];

        $pagePermissions = [];
        foreach ($pageMap as $page => $permission) {
            $pagePermissions[] = $permission;
            $permissions[] = $permission;
        }

        // Custom permissions (not auto-generated from resource prefixes)
        $customPermissions = [
            'assign_lead_sales::lead',
            'complete_sales::contract',
            'archive_sales::client',
            'complete_sales::client',
            'archive_sales::all::client',
            'complete_sales::all::client',
            'archive_sales::lead',
        ];

        foreach ($customPermissions as $permissionName) {
            $permissions[] = $permissionName;
        }

        // Create permissions
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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

            $companyManagementPerms = array_values(array_filter($companyResourcePermissions, fn ($p) => str_contains($p, 'core::company') && ! str_contains($p, 'connected_account')));

            if ($company->id == 1) {
                $documentDefaultPerms = array_values(array_filter($companyResourcePermissions, fn ($p) => str_contains($p, 'document::default') || str_contains($p, 'mail::')));
                $adminPermissions = array_merge($pagePermissions, $globalResourcePermissions, $documentDefaultPerms, $companyManagementPerms, $customPermissions);
            } else {
                // Admin sees AllClient (cross-company) instead of Client (company-scoped)
                $nonClientPerms = array_values(array_filter($companyResourcePermissions, fn ($p) => ! str_contains($p, 'sales::client')));
                $allClientPerms = array_values(array_filter($globalResourcePermissions, fn ($p) => str_contains($p, 'sales::all::client')));
                $estimateTemplatePerms = array_values(array_filter($globalResourcePermissions, fn ($p) => str_contains($p, 'sales::estimate::template')));
                $adminPermissions = array_merge($pagePermissions, $nonClientPerms, $allClientPerms, $estimateTemplatePerms, $customPermissions);
            }

            $adminRole->syncPermissions($adminPermissions);
            $this->command->info("Admin role and permissions synced for company: {$company->name}");

            $salesRole = Role::firstOrCreate([
                'name' => 'Sales',
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);

            $settingsPagePermissions = ['page_Reports', 'page_AccountChart', 'page_CompanyProfile', 'page_Localization', 'page_CompanyDefault', 'page_Quotation', 'page_ManageCompany'];
            $salesPagePermissions = array_values(array_filter($pagePermissions, static fn ($p) => ! in_array($p, $settingsPagePermissions)));
            $salesPermissions = array_merge($salesPagePermissions, [
                'view_mine_sales::lead',
                'view_sales::lead',
                'create_sales::lead',
                'update_sales::lead',
                'delete_sales::lead',
                'archive_sales::lead',
                'view_mine_sales::client',
                'view_sales::client',
                'create_sales::client',
                'update_sales::client',
                'delete_sales::client',
                'archive_sales::client',
                'complete_sales::client',
                'view_mine_sales::all::client',
                'view_sales::all::client',
                'create_sales::all::client',
                'update_sales::all::client',
                'delete_sales::all::client',
                'archive_sales::all::client',
                'complete_sales::all::client',
                'view_mine_sales::estimate',
                'create_sales::estimate',
                'update_sales::estimate',
                'delete_sales::estimate',
                'view_mine_sales::contract',
                'view_mine_sales::variation::order',
                'view_sales::variation::order',
                'view_any_sales::variation::order',
                'create_sales::variation::order',
                'update_sales::variation::order',
                'update_any_sales::variation::order',
                'delete_sales::variation::order',
                'delete_any_sales::variation::order',
                'restore_sales::variation::order',
                'restore_any_sales::variation::order',
                'replicate_sales::variation::order',
                'reorder_sales::variation::order',
                'force_delete_sales::variation::order',
                'force_delete_any_sales::variation::order',
            ]);

            $salesRole->syncPermissions($salesPermissions);
            $this->command->info("Sales role and permissions synced for company: {$company->name}");
        }

        $firstCompany = $companies->first();

        // 1. Seed Super Admin users
        $superAdmins = [
            [
                'name' => 'Admin',
                'email' => 'admin@erpsaas.com',
            ],
            [
                'name' => 'Super Admin Stylemyspace',
                'email' => 'enquiry@stylemyspace.com.sg',
            ],
            [
                'name' => 'Back Up Super Admin',
                'email' => 'addison.chai@hotmail.com',
            ],
        ];

        foreach ($superAdmins as $adminData) {
            $adminUser = User::where('email', $adminData['email'])->first();

            if (! $adminUser) {
                $adminUser = User::create([
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'current_company_id' => $firstCompany?->id ?? 1,
                ]);
                $this->command->info("User {$adminData['email']} created.");
            } else {
                $adminUser->update(['name' => $adminData['name']]);
            }

            if ($firstCompany && ! $adminUser->current_company_id) {
                $adminUser->switchCompany($firstCompany);
            }

            foreach ($companies as $company) {
                if (! $adminUser->belongsToCompany($company)) {
                    $adminUser->companies()->attach($company, ['role' => 'admin']);
                }

                $adminUser->assignRolesForCompany($company->id, $superAdminName);
                $this->command->info("User {$adminData['email']} assigned the {$superAdminName} role in company: {$company->name}");
            }
        }

        // 2. Seed Sales users
        $salesUsers = [
            [
                'name' => 'Addison',
                'email' => 'addison@stylemyspace.com.sg',
            ],
            [
                'name' => 'Brian',
                'email' => 'brian@stylemyspace.com.sg',
            ],
            [
                'name' => 'Macauly',
                'email' => 'macyap@stylemyspace.com.sg',
            ],
            [
                'name' => 'SC Leang',
                'email' => 'scleang@stylemyspace.com.sg',
            ],
        ];

        foreach ($salesUsers as $salesData) {
            $salesUser = User::where('email', $salesData['email'])->first();

            if (! $salesUser) {
                $salesUser = User::create([
                    'name' => $salesData['name'],
                    'email' => $salesData['email'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'current_company_id' => $firstCompany?->id ?? 1,
                ]);
                $this->command->info("User {$salesData['email']} created.");
            } else {
                $salesUser->update(['name' => $salesData['name']]);
            }

            if ($firstCompany && ! $salesUser->current_company_id) {
                $salesUser->switchCompany($firstCompany);
            }

            // Sales users should only belong to the ERP system company (first company)
            if ($firstCompany) {
                if (! $salesUser->belongsToCompany($firstCompany)) {
                    $salesUser->companies()->attach($firstCompany, ['role' => 'user']);
                }

                $salesUser->assignRolesForCompany($firstCompany->id, 'Sales');
                $this->command->info("User {$salesData['email']} assigned the Sales role in company: {$firstCompany->name}");
            }
        }

        // 3. Delete any other users to ensure we ONLY have the above allowed users
        $allowedEmails = array_merge(
            array_column($superAdmins, 'email'),
            array_column($salesUsers, 'email')
        );
        User::whereNotIn('email', $allowedEmails)->delete();
        $this->command->info("Cleaned up any unauthorized users.");
    }
}

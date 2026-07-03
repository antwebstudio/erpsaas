<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RevokeSalesLeadSourcePermissionSeeder extends Seeder
{
    /**
     * Revoke lead source permissions from the Sales role.
     *
     * The Sales role should not be able to view/manage lead sources.
     */
    public function run(): void
    {
        $leadSourcePermissions = Permission::where('name', 'like', '%_sales::lead::source')->get();

        if ($leadSourcePermissions->isEmpty()) {
            $this->command->info('No lead source permissions found, nothing to revoke.');

            return;
        }

        $companies = Company::all();

        foreach ($companies as $company) {
            $salesRole = Role::where([
                'name' => 'Sales',
                'guard_name' => 'web',
                'company_id' => $company->id,
            ])->first();

            if (! $salesRole) {
                continue;
            }

            $salesRole->revokePermissionTo($leadSourcePermissions);
            $this->command->info("Revoked lead source permissions from Sales role for company: {$company->name}");
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\AdjustmentType;
use App\Models\Accounting\Adjustment;
use App\Models\Company;
use App\Models\Setting\CompanyProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Enums\Setting\EntityType;
use Illuminate\Support\Facades\Storage;

class UserCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a single admin user first, then create their personal company separately.
        // Using withPersonalCompany() would double-call withCompanyProfile/withCompanyDefaults
        // (once in UserFactory defaults, once in the callback), causing a unique constraint
        // violation on document_defaults (company_id, type).
        $user = User::factory()
            ->create([
                'name' => 'Admin',
                'email' => 'admin@erpsaas.com',
                'password' => bcrypt('password'),
            ]);

        $primaryCompany = Company::factory()
            ->state([
                'name' => 'ERPSAAS',
                'user_id' => $user->id,
                'personal_company' => true,
            ])
            ->withCompanyProfile('SG')
            ->withCompanyDefaults('SGD', 'en')
            ->afterCreating(function (Company $company) {
                CompanyProfile::factory()
                    ->forCompany($company)
                    ->withAddress('SG')
                    ->create([
                        'entity_type' => EntityType::Corporation,
                    ]);
            })
            ->create();

        $user->update(['current_company_id' => $primaryCompany->id]);

        $stateId = \App\Models\Locale\State::where('country_id', 'SG')
            ->where('name', 'Central Singapore')
            ->first()?->id ?? \App\Models\Locale\State::where('country_id', 'SG')->first()?->id;

        $contacts = [
            'phone_number' => '+65 6242 5334',
            'email' => 'enquiry@stylemyspace.com.sg',
            'address' => [
                'type' => \App\Enums\Common\AddressType::General,
                'recipient' => 'Admin',
                'phone' => '+65 6242 5334',
                'address_line_1' => '8 BURN ROAD',
                'address_line_2' => '#01-10, TRIVEX BUILDING',
                'city' => 'Singapore',
                'state_id' => $stateId,
                'postal_code' => '369977',
                'country_code' => 'SG',
                'notes' => '',
            ],
        ];

        // Update the admin user's personal company profile and address
        if ($primaryCompany->profile) {
            $primaryCompany->profile->update([
                'phone_number' => $contacts['phone_number'],
                'email' => $contacts['email'],
            ]);
            $primaryCompany->profile->address->update($contacts['address']);
        }

        $additionalCompanies = [
            ['name' => 'Muyi Carpenters Pte Ltd', 'country' => 'SG', 'currency' => 'SGD', 'locale' => 'en'],
            ['name' => 'Stylemyspace Design Studio', 'country' => 'SG', 'currency' => 'SGD', 'locale' => 'en'],
            ['name' => 'Stylemyspace', 'country' => 'SG', 'currency' => 'SGD', 'locale' => 'en', 'default_sales_tax' => [
                        'name' => 'GST Tax',
                        'description' => 'Goods and Services Tax - 9%',
                        'rate' => 90000, // 9% (9 * 10000 scaling factor)
                        'computation' => AdjustmentComputation::Percentage,
                        'category' => AdjustmentCategory::Tax,
                        'type' => AdjustmentType::Sales,
                        'scope' => null,
                    ]],
        ];

        foreach ($additionalCompanies as $companyData) {
            $company = Company::factory()
                ->state([
                    'name' => $companyData['name'],
                    'user_id' => $user->id,
                    'personal_company' => false,
                ])
                ->withCompanyProfile($companyData['country'])
                ->withCompanyDefaults($companyData['currency'], $companyData['locale'])
                ->create();

            $company->profile->update([
                'phone_number' => $contacts['phone_number'],
                'email' => $contacts['email'],
            ]);
            $company->profile->address->update($contacts['address']);

            // Create GST Tax 9% and set as default sales tax for Stylemyspace
            if (isset($companyData['default_sales_tax'])) {
                $adjustment = Adjustment::create($companyData['default_sales_tax']);

                $company->profile->update([
                    'default_sales_tax_id' => $adjustment->id,
                ]);
            }
        }
    }
}


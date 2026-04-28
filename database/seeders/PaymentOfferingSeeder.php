<?php

namespace Database\Seeders;

use App\Enums\Common\OfferingType;
use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use App\Models\Company;
use Illuminate\Database\Seeder;

class PaymentOfferingSeeder extends Seeder
{
    /**
     * The payment type offerings to create under the "Payment" category.
     */
    private const PAYMENT_OFFERINGS = [
        ['name' => 'Deposit payment',              'sort_order' => 1],
        ['name' => 'Work commencement payment',     'sort_order' => 2],
        ['name' => 'Progressive payment',           'sort_order' => 3],
        ['name' => 'Variation order payment',       'sort_order' => 4],
        ['name' => 'Wiring work payment',           'sort_order' => 5],
        ['name' => 'Final payment',                 'sort_order' => 6],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $erpSystemCompanyId = config('erp.erp_system_company_id');

        Company::query()
            ->when($erpSystemCompanyId, fn ($query) => $query->where('id', '!=', $erpSystemCompanyId))
            ->get()
            ->each(function (Company $company) {
                $this->seedForCompany($company);
            });
    }

    private function seedForCompany(Company $company): void
    {
        // Force session for the model's save hook (OfferingCategory uses it)
        session(['current_company_id' => $company->id]);

        // Create or find the "Payment" category (root-level)
        $category = OfferingCategory::create(
            [
                'name'       => 'Payment',
                'company_id' => $company->id,
            ]
        );

        // Link the category to the company profile
        $company->profile()->updateOrCreate(
            ['company_id' => $company->id],
            ['payment_offering_category_id' => $category->id]
        );

        foreach (self::PAYMENT_OFFERINGS as $data) {
            /** @var Offering $offering */
            $offering = Offering::firstOrCreate(
                [
                    'name'       => $data['name'],
                    'company_id' => $company->id,
                ],
                [
                    'description' => $data['name'],
                    'type'        => OfferingType::Service,
                    'price'       => 0,
                    'sellable'    => true,
                    'purchasable' => false,
                    'sort_order'  => $data['sort_order'],
                ]
            );

            // Attach to the "Payment" category (idempotent)
            $offering->categories()->syncWithoutDetaching([$category->id]);
        }

        $this->command?->info("Seeded payment offerings for company: {$company->name}");
    }
}

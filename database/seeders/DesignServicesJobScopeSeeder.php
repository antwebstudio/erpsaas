<?php

namespace Database\Seeders;

use App\Enums\Common\OfferingType;
use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use App\Models\Company;
use Illuminate\Database\Seeder;

class DesignServicesJobScopeSeeder extends Seeder
{
    private const JOB_SCOPE_NAME = 'Design & Services';

    private const OPTIONS = [
        ['name' => 'Professional Design Consultation', 'sort_order' => 1],
        ['name' => 'Propose Space Planning',           'sort_order' => 2],
        ['name' => 'Detail Perspective Drawings',      'sort_order' => 3],
        ['name' => 'Budget Planning',                  'sort_order' => 4],
        ['name' => 'Materials & Colour Scheme Proposal', 'sort_order' => 5],
        ['name' => 'Site Co-ordination & Supervision', 'sort_order' => 6],
    ];

    public function run(): void
    {
        $erpSystemCompanyId = config('erp.erp_system_company_id');

        Company::query()
            ->when($erpSystemCompanyId, fn ($query) => $query->where('id', $erpSystemCompanyId))
            ->get()
            ->each(function (Company $company) {
                $this->seedForCompany($company);
            });
    }

    private function seedForCompany(Company $company): void
    {
        session(['current_company_id' => $company->id]);

        $category = OfferingCategory::firstOrCreate([
            'name'       => self::JOB_SCOPE_NAME,
            'company_id' => $company->id,
        ]);

        foreach (self::OPTIONS as $data) {
            $offering = Offering::updateOrCreate(
                [
                    'name'       => $data['name'],
                    'company_id' => $company->id,
                ],
                [
                    'type'        => OfferingType::Service,
                    'price'       => 0,
                    'sellable'    => true,
                    'purchasable' => false,
                    'sort_order'  => $data['sort_order'],
                ]
            );

            $offering->categories()->syncWithoutDetaching([$category->id]);
        }

        $this->command?->info("Seeded Design & Services job scope for company: {$company->name}");
    }
}

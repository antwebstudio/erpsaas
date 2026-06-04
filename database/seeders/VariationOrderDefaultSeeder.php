<?php

namespace Database\Seeders;

use App\Enums\Accounting\DocumentType;
use App\Models\Company;
use App\Models\Setting\DocumentDefault;
use Illuminate\Database\Seeder;

class VariationOrderDefaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::all()->each(function (Company $company) {
            $exists = DocumentDefault::query()
                ->where('company_id', $company->id)
                ->where('type', DocumentType::VariationOrder)
                ->exists();

            if (! $exists) {
                DocumentDefault::factory()
                    ->variationOrder()
                    ->create([
                        'company_id' => $company->id,
                        'created_by' => $company->user_id,
                        'updated_by' => $company->user_id,
                    ]);
            }
        });
    }
}

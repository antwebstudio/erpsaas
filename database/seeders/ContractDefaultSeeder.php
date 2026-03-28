<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Setting\DocumentDefault;
use App\Enums\Accounting\DocumentType;
use Illuminate\Database\Seeder;

class ContractDefaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::all()->each(function (Company $company) {
            $exists = DocumentDefault::query()
                ->where('company_id', $company->id)
                ->where('type', DocumentType::Contract)
                ->exists();

            if (!$exists) {
                DocumentDefault::factory()
                    ->contract()
                    ->create([
                        'company_id' => $company->id,
                        'created_by' => $company->user_id,
                        'updated_by' => $company->user_id,
                    ]);
            }
        });
    }
}

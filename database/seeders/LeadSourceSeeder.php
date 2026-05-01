<?php

namespace Database\Seeders;

use App\Models\Common\LeadSource;
use App\Models\Company;
use Illuminate\Database\Seeder;

class LeadSourceSeeder extends Seeder
{
    private array $sources = [
        ['name' => 'Website', 'description' => 'Organic traffic or contact form from the company website'],
        ['name' => 'Referral', 'description' => 'Referred by an existing client or business partner'],
        ['name' => 'Social Media', 'description' => 'Leads from Facebook, Instagram, LinkedIn, or other social platforms'],
        ['name' => 'Cold Call', 'description' => 'Outbound phone call initiated by the sales team'],
        ['name' => 'Email Campaign', 'description' => 'Inbound response to a marketing email campaign'],
        ['name' => 'Walk-in', 'description' => 'Prospect visited the office or showroom in person'],
        ['name' => 'Trade Show / Event', 'description' => 'Met at an exhibition, trade show, or networking event'],
        ['name' => 'Google Ads', 'description' => 'Paid search advertisement via Google'],
        ['name' => 'Word of Mouth', 'description' => 'Heard about the company through informal recommendations'],
        ['name' => 'Other', 'description' => 'Source not listed above'],
    ];

    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            foreach ($this->sources as $source) {
                LeadSource::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $source['name']],
                    ['description' => $source['description']],
                );
            }
        }

        $this->command->info('Lead sources seeded for ' . $companies->count() . ' company/companies.');
    }
}

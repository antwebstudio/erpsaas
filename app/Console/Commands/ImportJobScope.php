<?php

namespace App\Console\Commands;

use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use App\Enums\Common\OfferingType;
use App\Models\Company;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ImportJobScope extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-job-scope {filename} {--company= : The ID of the company to import data for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import JobScope data from an Excel file (1=JobScope, 2=JobScopeDescription, 3=JobScopeOption)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $filename = storage_path($filename);
        $companyId = $this->option('company');

        if (!$companyId) {
            $this->error('The --company option is required.');
            return 1;
        }

        $company = Company::find($companyId);
        if (!$company) {
            $this->error("Company with ID {$companyId} not found.");
            return 1;
        }

        if (!file_exists($filename)) {
            $this->error("File {$filename} not found.");
            return 1;
        }

        // Set company context for CompanyOwned trait
        session(['current_company_id' => $companyId]);

        $this->info("Importing JobScope data from {$filename} for company: {$company->name}...");

        try {
            $data = Excel::toArray([], $filename);
            $rows = $data[0] ?? [];

            if (empty($rows)) {
                $this->warn('The Excel file is empty.');
                return 0;
            }

            $currentJobScope = null;
            $currentJobScopeDescription = null;
            $importCount = [
                'JobScope' => 0,
                'JobScopeDescription' => 0,
                'JobScopeOption' => 0,
            ];

            $offeringOrder = 1;

            DB::transaction(function () use ($rows, $companyId, &$currentJobScope, &$currentJobScopeDescription, &$importCount, &$offeringOrder) {
                foreach ($rows as $index => $row) {
                    // Skip header if it exists (check if first row has "Type" or similar)
                    if ($index === 0 && (strcasecmp($row[0] ?? '', 'type') === 0 || strcasecmp($row[1] ?? '', 'name') === 0)) {
                        continue;
                    }

                    $type = (int) ($row[0] ?? 0);
                    $name = trim($row[1] ?? '');

                    if (empty($name) && empty($type)) {
                        continue;
                    }

                    if (empty($name)) {
                        $this->warn("Row " . ($index + 1) . ": Name is empty. Skipping.");
                        continue;
                    }

                    $description = $row[2] ?? null;

                    switch ($type) {
                        case 1: // JobScope (Root Category)
                            $currentJobScope = new OfferingCategory();
                            $currentJobScope->company_id = $companyId;
                            $currentJobScope->name = $name;
                            $currentJobScope->parent_id = null;
                            
                            $currentJobScope->description = $description;
                            $currentJobScope->save();

                            $currentJobScopeDescription = null; // Reset description context
                            $importCount['JobScope']++;
                            break;

                        case 2: // JobScopeDescription (Child Category)
                            if (!$currentJobScope) {
                                $this->warn("Row " . ($index + 1) . ": Item type 2 found before any type 1. Skipping.");
                                continue 2;
                            }

                            $currentJobScopeDescription = new OfferingCategory();
                            $currentJobScopeDescription->company_id = $companyId;
                            $currentJobScopeDescription->name = $name;
                            $currentJobScopeDescription->parent_id = $currentJobScope->id;

                            $currentJobScopeDescription->description = $description;
                            $currentJobScopeDescription->save();

                            $importCount['JobScopeDescription']++;
                            break;

                        case 3: // JobScopeOption (Offering)
                            $targetCategory = $currentJobScopeDescription ?: $currentJobScope;

                            if (!$targetCategory) {
                                $this->warn("Row " . ($index + 1) . ": Item type 3 found before any type 1 or 2. Skipping.");
                                continue 2;
                            }

                            $price = $row[3] ?? 0;
                            $unit = $row[4] ?? null;

                            $offering = new Offering();
                            $offering->company_id = $companyId;
                            $offering->name = $name;
                            $offering->type = OfferingType::Service;

                            $offering->description = $description;
                            $offering->price = $price;
                            $offering->unit = $unit;
                            $offering->sellable = true;
                            $offering->purchasable = false;
                            $offering->sort_order = $offeringOrder++;
                            $offering->save();

                            // Link to category if not already linked
                            $offering->categories()->attach($targetCategory->id);
                            
                            $importCount['JobScopeOption']++;
                            break;

                        default:
                            if ($type > 0) {
                                $this->warn("Row " . ($index + 1) . ": Unknown type {$type}. Skipping.");
                            }
                            break;
                    }
                }
            });

            $this->info("Import completed successfully!");
            $this->table(['Type', 'Count'], [
                ['JobScope', $importCount['JobScope']],
                ['JobScopeDescription', $importCount['JobScopeDescription']],
                ['JobScopeOption', $importCount['JobScopeOption']],
            ]);

        } catch (\Exception $e) {
            $this->error("An error occurred during import: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\EstimateStatus;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Estimate;
use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportEstimateTemplate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-estimate-template {filename} {--company= : The ID of the company} {--name= : The template name/header}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an Estimate Template from an Excel file. Matches column B text against Offering or OfferingCategory names.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $filename = storage_path($filename);
        $companyId = $this->option('company');
        $templateName = $this->option('name');

        if (! $companyId) {
            $this->error('The --company option is required.');

            return 1;
        }

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company with ID {$companyId} not found.");

            return 1;
        }

        if (! file_exists($filename)) {
            $this->error("File {$filename} not found.");

            return 1;
        }

        // Set company context for CompanyOwned trait
        session(['current_company_id' => $companyId]);

        $this->info("Importing Estimate Template from {$filename} for company: {$company->name}...");

        try {
            $data = Excel::toArray([], $filename);
            $rows = $data[0] ?? [];

            if (empty($rows)) {
                $this->warn('The Excel file is empty.');

                return 0;
            }

            $counts = [
                'groups' => 0,
                'items' => 0,
                'skipped' => 0,
            ];

            DB::transaction(function () use ($rows, $companyId, $company, $templateName, &$counts) {
                // 1. Create the Estimate template
                $estimate = Estimate::create([
                    'company_id' => $companyId,
                    'client_id' => null,
                    'estimate_number' => Estimate::getNextDocumentNumber($company),
                    'header' => $templateName ?? 'Imported Template',
                    'subheader' => '',
                    'date' => now(),
                    'expiration_date' => now()->addDays(30),
                    'status' => EstimateStatus::Draft,
                    'currency_code' => $company->currency_code ?? 'USD',
                    'discount_method' => DocumentDiscountMethod::PerLineItem,
                    'discount_computation' => AdjustmentComputation::Percentage,
                    'discount_rate' => 0,
                    'subtotal' => 0,
                    'tax_total' => 0,
                    'discount_total' => 0,
                    'total' => 0,
                    'is_template' => true,
                    'terms' => '',
                    'footer' => '',
                ]);

                $this->info("Created Estimate Template: {$estimate->estimate_number}");

                $currentParentGroup = null;
                $currentSubGroup = null;
                $groupOrder = 1;
                $subGroupOrder = 1;
                $lineNumber = 1;

                foreach ($rows as $index => $row) {
                    // Skip header row if detected
                    if ($index === 0 && (strcasecmp($row[0] ?? '', 'type') === 0 || strcasecmp($row[0] ?? '', 'level') === 0 || strcasecmp($row[1] ?? '', 'name') === 0)) {
                        continue;
                    }

                    $rawLevel = (int) ($row[0] ?? 0);
                    $text = trim($row[1] ?? '');

                    if (empty($text)) {
                        continue;
                    }

                    // The raw file's level column is not trustworthy — it can disagree with the
                    // structure already established in works.xlsx (JobScope/JobScopeDescription/
                    // JobScopeOption). Instead, resolve the actual level from the matching
                    // OfferingCategory/Offering records imported from works.xlsx.
                    $category = OfferingCategory::where('company_id', $companyId)
                        ->where('name', $text)
                        ->first();

                    if ($category) {
                        if ($category->parent_id === null) {
                            // Root category -> Main Group
                            $currentParentGroup = DocumentLineItemGroup::create([
                                'documentable_type' => $estimate->getMorphClass(),
                                'documentable_id' => $estimate->id,
                                'company_id' => $companyId,
                                'offering_category_id' => $category->id,
                                'name' => $category->name,
                                'order' => $groupOrder++,
                                'parent_id' => null,
                            ]);

                            $currentSubGroup = null;
                            $subGroupOrder = 1;
                            $counts['groups']++;
                            $this->line("  + Main Group: {$text}");
                        } else {
                            // Child category -> Sub-Group
                            $currentSubGroup = DocumentLineItemGroup::create([
                                'documentable_type' => $estimate->getMorphClass(),
                                'documentable_id' => $estimate->id,
                                'company_id' => $companyId,
                                'offering_category_id' => $category->id,
                                'name' => $category->name,
                                'order' => $subGroupOrder++,
                                'parent_id' => $currentParentGroup?->id,
                            ]);

                            $counts['groups']++;
                            $this->line("    + Sub-Group: {$text}");
                        }

                        continue;
                    }

                    $offering = Offering::where('company_id', $companyId)
                        ->where('name', $text)
                        ->first();

                    if ($offering) {
                        // Offering -> line item, nested under the current sub-group if one is open,
                        // otherwise under the current main group.
                        DocumentLineItem::create([
                            'documentable_type' => $estimate->getMorphClass(),
                            'documentable_id' => $estimate->id,
                            'company_id' => $companyId,
                            'group_id' => $currentSubGroup?->id ?? $currentParentGroup?->id,
                            'offering_id' => $offering->id,
                            'description' => $offering->description ?? $offering->name,
                            'quantity' => 1,
                            'unit_price' => $offering->price ?? 0,
                            'subtotal' => $offering->price ?? 0,
                            'total' => $offering->price ?? 0,
                            'unit' => $offering->unit,
                            'line_number' => $lineNumber++,
                            'tax_total' => 0,
                            'discount_total' => 0,
                            'is_locked' => 1,
                        ]);

                        $counts['items']++;
                        $this->line("    + Item: {$text} (in " . ($currentSubGroup ? 'sub-group' : 'main group') . ')');

                        continue;
                    }

                    // No matching Offering/OfferingCategory found in works.xlsx-derived data.
                    $counts['skipped']++;
                    $this->warn('  Row ' . ($index + 1) . ": \"{$text}\" skipped (no matching Offering/OfferingCategory found; raw file level={$rawLevel}).");
                }

                // Recalculate totals
                $subtotal = $estimate->lineItems()->sum('unit_price');
                $estimate->update([
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                ]);
            });

            $this->newLine();
            $this->info('Import completed successfully!');
            $this->table(['Metric', 'Count'], [
                ['Groups created', $counts['groups']],
                ['Items added', $counts['items']],
                ['Rows skipped', $counts['skipped']],
            ]);

        } catch (\Exception $e) {
            $this->error('An error occurred during import: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return 1;
        }

        return 0;
    }
}

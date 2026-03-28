<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Setting\DocumentDefault;
use App\Enums\Accounting\DocumentType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class InitTemplates extends Command
{
    protected $signature = 'app:init-templates';
    protected $description = 'Import templates and set document defaults for companies.';

    private array $skipped = [];

    public function handle()
    {
        $this->info('Starting template initialization...');

        $this->importEstimateTemplates();
        $this->importJobScopes();
        $this->setDefaultBackgroundImages();
        $this->setDefaultCoverPdfs();
        $this->setDefaultDocumentHtmlContent();

        $this->showSkipped();

        $this->info('Template initialization complete!');
    }

    private function importEstimateTemplates()
    {
        $this->info('Function 1: Importing Estimate Templates...');

        $path = storage_path('template/raw');
        if (!File::isDirectory($path)) {
            $this->skipped[] = "Directory missing: {$path}";
            return;
        }

        $files = File::files($path);
        if (empty($files)) {
            $this->skipped[] = "No files found in: {$path}";
            return;
        }

        foreach ($files as $file) {
            if ($file->getExtension() !== 'xlsx') continue;

            $filename = $file->getFilename();
            $relativePath = 'template/raw/' . $filename;
            $name = $file->getBasename('.' . $file->getExtension());

            $this->info("Importing template: {$name} from {$filename}");

            $exitCode = Artisan::call('app:import-estimate-template', [
                'filename' => $relativePath,
                '--company' => 1,
                '--name' => $name
            ]);

            if ($exitCode !== 0) {
                $this->skipped[] = "Failed to import estimate template: {$filename}";
            }
        }
    }

    private function importJobScopes()
    {
        $this->info('Function 2: Importing Job Scopes...');

        $dirPath = storage_path('template/works');
        $filePath = storage_path('template/works.xlsx');

        if (File::isDirectory($dirPath)) {
            $files = File::files($dirPath);
            foreach ($files as $file) {
                if ($file->getExtension() !== 'xlsx') continue;
                $filename = $file->getFilename();
                $relativePath = 'template/works/' . $filename;
                $this->info("Importing Job Scope: {$filename}");
                Artisan::call('app:import-job-scope', [
                    'filename' => $relativePath,
                    '--company' => 1
                ]);
            }
            if (empty($files)) {
                $this->skipped[] = "No files found in directory: {$dirPath}";
            }
        } elseif (File::exists($filePath)) {
            $this->info("Importing Job Scope: template/works.xlsx");
            Artisan::call('app:import-job-scope', [
                'filename' => 'template/works.xlsx',
                '--company' => 1
            ]);
        } else {
            $this->skipped[] = "Neither directory 'template/works' nor file 'template/works.xlsx' found.";
        }
    }

    private function setDefaultBackgroundImages()
    {
        $this->info('Function 3: Setting Default Background Images...');

        $companyMap = [
            2 => 'muyi-carpenter.png',
            4 => 'stylemyspace.png',
            3 => 'stylemyspace-design-studio.png',
        ];

        foreach ($companyMap as $id => $filename) {
            $sourcePath = storage_path("template/background/{$filename}");
            if (!File::exists($sourcePath)) {
                $this->skipped[] = "Background image missing for company {$id}: {$filename}";
                continue;
            }

            $storagePath = "settings/background/{$id}_{$filename}";
            Storage::disk('public')->put($storagePath, File::get($sourcePath));

            foreach ([DocumentType::Estimate, DocumentType::VariationOrder, DocumentType::Contract, DocumentType::Invoice] as $type) {
                $updated = DocumentDefault::where('company_id', $id)
                    ->where('type', $type)
                    ->update(['background_image' => $storagePath]);

                if (!$updated) {
                    DocumentDefault::create([
                        'company_id' => $id,
                        'type' => $type,
                        'background_image' => $storagePath
                    ]);
                }
            }
            
            $this->info("Updated background for company {$id} (Estimate)");
        }
    }

    private function setDefaultCoverPdfs()
    {
        $this->info('Function 4: Setting Default Cover PDFs...');

        $companyMap = [
            2 => 'muyi-carpenter.png',
            4 => 'stylemyspace.png',
            3 => 'stylemyspace-design-studio.png',
        ];

        foreach ($companyMap as $id => $filename) {
            $sourcePath = storage_path("template/cover/{$filename}");
            if (!File::exists($sourcePath)) {
                $this->skipped[] = "Cover image missing for company {$id}: {$filename}";
                continue;
            }

            $storagePath = "settings/cover/{$id}_{$filename}";
            Storage::disk('public')->put($storagePath, File::get($sourcePath));

            $updated = DocumentDefault::where('company_id', $id)
                ->where('type', DocumentType::Estimate)
                ->update(['cover_pdf' => $storagePath]);

            if (!$updated) {
                 DocumentDefault::create([
                    'company_id' => $id,
                    'type' => DocumentType::Estimate,
                    'cover_pdf' => $storagePath
                ]);
            }
            $this->info("Updated cover for company {$id} (Estimate)");
        }
    }

    private function setDefaultDocumentHtmlContent()
    {
        $this->info('Function 5: Setting Default Terms & Materials Guide HTML...');

        $path = storage_path('template/document');
        if (!File::isDirectory($path)) {
            $this->skipped[] = "Directory missing: {$path}";
            return;
        }

        $ids = [2, 3, 4];

        foreach ($ids as $id) {
            $updateData = [];

            // Terms and conditions
            $termsFile = "terms_and_conditions_{$id}_estimate.html";
            $termsPath = $path . DIRECTORY_SEPARATOR . $termsFile;
            if (File::exists($termsPath)) {
                $updateData['terms_and_conditions'] = File::get($termsPath);
                $this->info("Read terms for company {$id}");
            } else {
                $this->skipped[] = "Terms file missing for company {$id}: {$termsFile}";
            }

            // Materials guide
            $guideFile = "materials_guide_{$id}_estimate.html";
            $guidePath = $path . DIRECTORY_SEPARATOR . $guideFile;
            if (File::exists($guidePath)) {
                $updateData['materials_guide'] = File::get($guidePath);
                $this->info("Read materials guide for company {$id}");
            } else {
                $this->skipped[] = "Materials guide file missing for company {$id}: {$guideFile}";
            }

            if (!empty($updateData)) {
                DocumentDefault::updateOrCreate(
                    ['company_id' => $id, 'type' => DocumentType::Estimate],
                    $updateData
                );
                $this->info("Applied document settings for company {$id} (Estimate)");
            }
        }
    }

    private function showSkipped()
    {
        $this->newLine();
        if (empty($this->skipped)) {
            $this->info('All files processed successfully, nothing skipped.');
            return;
        }

        $this->warn('The following files/directories were missing or skipped:');
        foreach ($this->skipped as $skip) {
            $this->line("- {$skip}");
        }
    }
}


<?php

namespace App\Services;

use App\DTO\DocumentDTO;
use App\Enums\Setting\Template;
use App\Models\Accounting\Estimate;
use App\Models\Setting\DocumentDefault;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Spatie\LaravelPdf\Facades\Pdf;

class EstimatePdfService
{
    /**
     * Generates a PDF for the given Estimate and returns the raw PDF binary string.
     */
    public function generate(Estimate $estimate): string
    {
        ini_set('memory_limit', '2048M');

        $documentTypeEnum = $estimate::documentType();
        $defaults = DocumentDefault::query()
            ->type($documentTypeEnum)
            ->first();

        $template = $defaults?->template ?? Template::Default;
        $document = DocumentDTO::fromModel($estimate);

        $html = view('pdf.estimate.estimate', [
            'document' => $document,
            'template' => $template,
            'record' => $estimate,
        ])->render();

        $pdfBase64 = Pdf::html($html)
            // ->withBrowsershot(function ($browsershot) {
            //     $browsershot->setNodeBinary('C:\Program Files\nodejs\node.exe')
            //         ->setNodeModulePath('C:\Users\chy19\AppData\Roaming\npm\node_modules')
            //         ->timeout(120)
            //         ->showBackground()
            //         ->margins(0, 0, 0, 0)
            //         ->addChromiumArguments(['no-sandbox', 'disable-setuid-sandbox']);
            // })
            ->format('a4')
            ->base64();

        $pdfString = base64_decode($pdfBase64);

        $pdf = new Fpdi;

        // 1. Prepend Cover PDF
        $coverPath = $defaults?->cover_pdf ? Storage::disk('public')->path($defaults->cover_pdf) : null;

        // Handle template company override if exists
        if ($estimate->template_company_id) {
            $templateDefaults = DocumentDefault::withoutGlobalScopes()
                ->where('company_id', $estimate->template_company_id)
                ->type($documentTypeEnum)
                ->first();

            if ($templateDefaults?->cover_pdf) {
                $coverPath = Storage::disk('public')->path($templateDefaults->cover_pdf);
            }
        }

        if ($coverPath && file_exists($coverPath)) {
            $extension = strtolower(pathinfo($coverPath, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Handle Image Cover
                $pdf->AddPage('P', [210, 297]); // A4
                $pdf->Image($coverPath, 0, 0, 210, 297);
            } else {
                // Handle PDF Cover
                $pageCount = $pdf->setSourceFile($coverPath);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], $size);
                    $pdf->useTemplate($templateId);
                }
            }
        }

        // 2. Append Generated Estimate PDF pages
        $tempEstimate = tempnam(sys_get_temp_dir(), 'est_');
        file_put_contents($tempEstimate, $pdfString);

        $pageCount = $pdf->setSourceFile($tempEstimate);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], $size);
            $pdf->useTemplate($templateId);
        }

        $finalPdfOutput = $pdf->Output('S');

        if (file_exists($tempEstimate)) {
            unlink($tempEstimate);
        }

        return $finalPdfOutput;
    }
}

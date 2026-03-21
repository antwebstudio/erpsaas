<?php

namespace App\Services;

use App\Models\Accounting\VariationOrder;
use App\Models\Setting\DocumentDefault;
use App\DTO\DocumentDTO;
use App\Enums\Setting\Template;
use Spatie\LaravelPdf\Facades\Pdf;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;

class VariationOrderPdfService
{
    /**
     * Generates a PDF for the given Variation Order and returns the raw PDF binary string.
     */
    public function generate(VariationOrder $variationOrder): string
    {
        ini_set('memory_limit', '2048M');
        
        $documentTypeEnum = $variationOrder::documentType();
        $defaults = DocumentDefault::query()
            ->type($documentTypeEnum)
            ->first();

        $template = $defaults?->template ?? Template::Default;
        $document = DocumentDTO::fromModel($variationOrder);

        $html = view('pdf.variation-order.variation-order', [
            'document' => $document,
            'template' => $template,
            'record' => $variationOrder,
        ])->render();

        $pdfBase64 = Pdf::html($html)
            ->withBrowsershot(function ($browsershot) {
                $browsershot->setNodeBinary('C:\Program Files\nodejs\node.exe')
                    ->setNodeModulePath('C:\Users\chy19\AppData\Roaming\npm\node_modules')
                    ->timeout(120)
                    ->showBackground()
                    ->margins(0, 0, 0, 0)
                    ->addChromiumArguments(['no-sandbox', 'disable-setuid-sandbox']);
            })
            ->format('a4')
            ->base64();
            
        $pdfString = base64_decode($pdfBase64);
        
        $pdf = new Fpdi();

        // 1. Prepend Cover PDF
        $coverPath = resource_path('quotation-template/cover.pdf');
        if (file_exists($coverPath)) {
            $pageCount = $pdf->setSourceFile($coverPath);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], $size);
                $pdf->useTemplate($templateId);
            }
        }

        // 2. Append Generated VariationOrder PDF pages
        $tempVO = tempnam(sys_get_temp_dir(), 'vo_');
        file_put_contents($tempVO, $pdfString);

        $pageCount = $pdf->setSourceFile($tempVO);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], $size);
            $pdf->useTemplate($templateId);
        }

        $finalPdfOutput = $pdf->Output('S');
        
        if (file_exists($tempVO)) {
            unlink($tempVO);
        }

        return $finalPdfOutput;
    }
}

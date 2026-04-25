<?php

namespace App\Services;

use App\Models\Accounting\Invoice;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        ini_set('memory_limit', '2048M');

        $invoice->loadMissing([
            'client.billingAddress',
            'lineItems.offering',
            'salesTaxes',
            'company.profile.address',
            'estimate.templateCompany.profile.address',
            'estimate.company.profile.address',
            'createdBy',
        ]);

        // Use template company from linked contract/estimate, then the estimate's own company, then the invoice's company
        $issuingCompany = $invoice->estimate?->templateCompany
            ?? $invoice->estimate?->company
            ?? $invoice->company;

        $issuingCompany->loadMissing(['profile.address']);

        $html = view('pdf.invoice.invoice', [
            'invoice' => $invoice,
            'issuingCompany' => $issuingCompany,
        ])->render();

        $pdfBase64 = Pdf::html($html)->format('a4')->base64();

        return base64_decode($pdfBase64);
    }
}

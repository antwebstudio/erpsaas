<?php

use App\Http\Controllers\DocumentPrintController;
use App\Http\Middleware\AllowSameOriginFrame;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPdf\Facades\Pdf;

Route::get('/', function () {
    return redirect(Filament::getDefaultPanel()->getUrl());
});

Route::middleware(['auth'])->group(function () {
    Route::get('documents/{documentType}/{id}/print', [DocumentPrintController::class, 'show'])
        ->middleware(AllowSameOriginFrame::class)
        ->name('documents.print');

    Route::get('invoices/{invoiceId}/switch-and-edit', function ($invoiceId) {
        $invoice = \App\Models\Accounting\Invoice::withoutGlobalScopes()->findOrFail($invoiceId);
        $company = $invoice->company;
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\InvoiceResource::getUrl('edit', ['record' => $invoice, 'tenant' => $company], panel: 'company'));
    })->name('invoices.switch-and-edit');

    Route::get('invoices/{invoiceId}/switch-and-view', function ($invoiceId) {
        $invoice = \App\Models\Accounting\Invoice::withoutGlobalScopes()->findOrFail($invoiceId);
        $company = $invoice->company;
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\InvoiceResource::getUrl('view', ['record' => $invoice, 'tenant' => $company], panel: 'company'));
    })->name('invoices.switch-and-view');

    Route::get('invoices/{invoiceId}/switch-and-record-payment', function ($invoiceId) {
        $invoice = \App\Models\Accounting\Invoice::withoutGlobalScopes()->findOrFail($invoiceId);
        $company = $invoice->company;
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\InvoiceResource\Pages\RecordPayments::getUrl([
            'tableFilters' => [
                'client_id' => ['value' => $invoice->client_id],
                'currency_code' => ['value' => $invoice->currency_code],
            ],
            'invoiceId' => $invoice->id,
            'tenant' => $company->id,
        ], panel: 'company'));
    })->name('invoices.switch-and-record-payment');

    Route::get('contracts/{contractId}/switch-and-view', function ($contractId) {
        $contract = \App\Models\Accounting\Contract::withoutGlobalScopes()->findOrFail($contractId);
        $company = \App\Models\Company::findOrFail(config('erp.erp_system_company_id'));
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\ContractResource::getUrl('view', ['record' => $contract, 'tenant' => $company], panel: 'company'));
    })->name('contracts.switch-and-view');

    Route::get('estimates/{estimateId}/switch-and-view', function ($estimateId) {
        $estimate = \App\Models\Accounting\Estimate::withoutGlobalScopes()->findOrFail($estimateId);
        $company = \App\Models\Company::findOrFail(config('erp.erp_system_company_id'));
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\EstimateResource::getUrl('view', ['record' => $estimate, 'tenant' => $company], panel: 'company'));
    })->name('estimates.switch-and-view');

    Route::get('variation-orders/{variationOrderId}/switch-and-view', function ($variationOrderId) {
        $variationOrder = \App\Models\Accounting\VariationOrder::withoutGlobalScopes()->findOrFail($variationOrderId);
        $company = \App\Models\Company::findOrFail(config('erp.erp_system_company_id'));
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\VariationOrderResource::getUrl('view', ['record' => $variationOrder, 'tenant' => $company], panel: 'company'));
    })->name('variation-orders.switch-and-view');

    Route::get('estimates/switch-and-create', function () {
        $company = \App\Models\Company::findOrFail(config('erp.erp_system_company_id'));
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\EstimateResource\Pages\CreateEstimate::getUrl(
            array_filter(['client' => request('client'), 'tenant' => $company->id]),
            panel: 'company'
        ));
    })->name('estimates.switch-and-create');

    Route::get('variation-orders/switch-and-create', function () {
        $company = \App\Models\Company::findOrFail(config('erp.erp_system_company_id'));
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\Company\Resources\Sales\VariationOrderResource\Pages\CreateVariationOrder::getUrl(
            array_filter(['client' => request('client'), 'tenant' => $company->id]),
            panel: 'company'
        ));
    })->name('variation-orders.switch-and-create');

    Route::get('quotation-builder/switch-and-open', function () {
        $company = \App\Models\Company::findOrFail(config('erp.erp_system_company_id'));
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\User\Pages\CreateQuotation::getUrl(
            array_filter(['tenant' => $company->id, 'client' => request('client'), 'estimate_id' => request('estimate_id')]),
            panel: 'user'
        ));
    })->name('quotation-builder.switch-and-open');

    Route::get('variation-order-builder/switch-and-open', function () {
        $company = \App\Models\Company::findOrFail(config('erp.erp_system_company_id'));
        auth()->user()->switchCompany($company);

        return redirect(\App\Filament\User\Pages\CreateVariationOrder::getUrl(
            array_filter(['tenant' => $company->id, 'client' => request('client'), 'variation_order_id' => request('variation_order_id')]),
            panel: 'user'
        ));
    })->name('variation-order-builder.switch-and-open');
});

Route::get('/download-quotation-pdf', function () {
    $html = file_get_contents(resource_path('quotation-template.html'));
    
    return Pdf::html($html)
        ->withBrowsershot(function ($browsershot) {
            $browsershot->setNodeBinary('C:\Program Files\nodejs\node.exe')
                ->setNodeModulePath('C:\Users\chy19\AppData\Roaming\npm\node_modules')
                ->timeout(120)
                ->addChromiumArguments(['no-sandbox', 'disable-setuid-sandbox']);
        })
        ->format('a4')
        ->download('quotation.pdf');
});

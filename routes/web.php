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

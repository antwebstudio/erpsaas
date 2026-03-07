<?php

namespace App\Jobs;

use App\Models\Accounting\Estimate;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateEstimatePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Estimate $estimate,
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '2048M');
        
        $documentTypeEnum = $this->estimate::documentType();
        $defaults = \App\Models\Setting\DocumentDefault::query()
            ->type($documentTypeEnum)
            ->first();

        $template = $defaults?->template ?? \App\Enums\Setting\Template::Default;
        $document = \App\DTO\DocumentDTO::fromModel($this->estimate);

        $html = \Illuminate\Support\Facades\Blade::render(
            file_get_contents(resource_path('quotation-template.html')), [
                'document' => $document,
                'template' => $template,
                'record' => $this->estimate,
            ]
        );

        $pdfBase64 = \Spatie\LaravelPdf\Facades\Pdf::html($html)
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
        
        $pdf = new \setasign\Fpdi\Fpdi();

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
        
        $fileName = "estimates/Estimate-{$this->estimate->documentNumber()}.pdf";
        Storage::disk('public')->put($fileName, $finalPdfOutput);
        
        Notification::make()
            ->title('Estimate PDF Generated successfully')
            ->body("Estimate {$this->estimate->documentNumber()} is ready to download.")
            ->success()
            ->actions([
                Action::make('download')
                    ->button()
                    ->url(Storage::url($fileName), shouldOpenInNewTab: true),
            ])
            ->sendToDatabase($this->user);
    }
}

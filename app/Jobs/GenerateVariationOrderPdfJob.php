<?php

namespace App\Jobs;

use App\Models\Accounting\VariationOrder;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateVariationOrderPdfJob implements ShouldQueue
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
        public VariationOrder $variationOrder,
        public User $user,
        public ?string $jobId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $pdfService = new \App\Services\VariationOrderPdfService();
            $finalPdfOutput = $pdfService->generate($this->variationOrder);
            
            $fileName = "variation_orders/VariationOrder-{$this->variationOrder->documentNumber()}.pdf";
            Storage::disk('public')->put($fileName, $finalPdfOutput);
            
            $fileUrl = Storage::url($fileName);

            if ($this->jobId) {
                \Illuminate\Support\Facades\Cache::put("pdf_job_{$this->jobId}", [
                    'status' => 'completed',
                    'url' => $fileUrl,
                ], now()->addMinutes(10));
            } else {
                Notification::make()
                    ->title('Variation Order PDF Generated successfully')
                    ->body("Variation Order {$this->variationOrder->documentNumber()} is ready to download.")
                    ->success()
                    ->actions([
                        Action::make('download')
                            ->button()
                            ->url($fileUrl, shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($this->user);
            }
        } catch (\Exception $e) {
            if ($this->jobId) {
                \Illuminate\Support\Facades\Cache::put("pdf_job_{$this->jobId}", [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ], now()->addMinutes(10));
            } else {
                Notification::make()
                    ->title('Variation Order PDF Generation failed')
                    ->body("There was an error generating the PDF for Variation Order {$this->variationOrder->documentNumber()}: {$e->getMessage()}")
                    ->danger()
                    ->sendToDatabase($this->user);
            }
        }
    }
}

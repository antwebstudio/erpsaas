<?php

namespace App\Livewire;

use App\Jobs\GenerateVariationOrderPdfJob;
use App\Models\Accounting\VariationOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component;

class VariationOrderPdfDownload extends Component
{
    public ?VariationOrder $record = null;

    public string $jobId;

    public string $status = 'pending';

    public ?string $downloadUrl = null;

    public ?string $errorMessage = null;

    public function mount(VariationOrder $record)
    {
        $this->record = $record;
        $this->jobId = (string) Str::uuid();

        Cache::put("pdf_job_{$this->jobId}", [
            'status' => 'pending',
        ], now()->addMinutes(10));

        /** @var \App\Models\User $user */
        $user = auth()->user();
        GenerateVariationOrderPdfJob::dispatch($this->record, $user, $this->jobId);
    }

    public function checkStatus()
    {
        if ($this->status !== 'pending') {
            return;
        }

        $jobData = Cache::get("pdf_job_{$this->jobId}");

        if ($jobData) {
            if ($jobData['status'] === 'completed') {
                $this->status = 'completed';
                $this->downloadUrl = $jobData['url'];
                $this->dispatch('close-modal', id: 'pdf-download-modal');
                $this->dispatch('open-url', url: $this->downloadUrl);
            } elseif ($jobData['status'] === 'failed') {
                $this->status = 'failed';
                $this->errorMessage = $jobData['error'] ?? 'Unknown error occurred.';
            }
        }
    }

    public function render()
    {
        return view('livewire.variation-order-pdf-download');
    }
}

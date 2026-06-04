<?php

namespace App\Filament\Company\Resources\Sales\VariationOrderResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Models\Accounting\VariationOrder;
use App\Models\Common\Client;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

class CreateVariationOrder extends CreateRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = VariationOrderResource::class;

    #[Url(as: 'client')]
    public ?int $clientId = null;

    public function mount(): void
    {
        parent::mount();

        if ($this->clientId) {
            $this->data['client_id'] = $this->clientId;

            if ($currencyCode = Client::find($this->clientId)?->currency_code) {
                $this->data['currency_code'] = $currencyCode;
            }
        }
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var VariationOrder $record */
        $data = collect($data)->reject(fn ($value) => is_null($value))->toArray();
        $record = parent::handleRecordCreation($data);

        $this->handleLineItems($record, collect($data['lineItemGroups'] ?? []));

        $totals = $this->updateDocumentTotals($record, $data);

        $record->updateQuietly($totals);

        return $record;
    }

    protected function afterCreate(): void
    {
        $taxKey = $this->record::documentType()->getTaxKey();
        $taxIds = $this->data[$taxKey] ?? null;

        if ($taxIds !== null) {
            $this->record->{$taxKey}()->withoutGlobalScopes()->sync($taxIds);
        }
    }
}

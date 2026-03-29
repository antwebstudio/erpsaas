<?php

namespace App\Filament\Company\Resources\Sales\VariationOrderResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Models\Accounting\VariationOrder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateVariationOrder extends CreateRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = VariationOrderResource::class;

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

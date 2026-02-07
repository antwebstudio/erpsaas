<?php

namespace App\Filament\Company\Resources\Sales\EstimateResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Models\Accounting\Estimate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditEstimate extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = EstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToBuilder')
                ->label('Back to Page Builder')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => \App\Filament\User\Pages\CreateQuotation::getUrl([
                    'estimate_id' => $this->getRecord()->id,
                    'client' => $this->getRecord()->client_id,
                ], panel: 'user')),
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Estimate $record */
        $lineItems = collect($data['lineItemGroups'] ?? []);

        $this->deleteRemovedLineItems($record, $lineItems);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $data = array_merge($data, $totals);

        return parent::handleRecordUpdate($record, $data);
    }
}

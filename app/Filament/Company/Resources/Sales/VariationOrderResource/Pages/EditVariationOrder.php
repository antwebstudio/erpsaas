<?php

namespace App\Filament\Company\Resources\Sales\VariationOrderResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Models\Accounting\VariationOrder;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditVariationOrder extends EditRecord
{
    use HandlePageRedirect;
    use ManagesLineItems;

    protected static string $resource = VariationOrderResource::class;

    public function mount(int | string $record): void
    {
        ini_set('memory_limit', '1024M');
        config(['app.disable_custom_select_relationships' => true]);

        parent::mount($record);
    }

    public function hydrate(): void
    {
        config(['app.disable_custom_select_relationships' => true]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function resolveRecord(int | string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'lineItemGroups' => fn ($query) => $query->whereNull('parent_id'),
            'lineItemGroups.offeringCategory',
            'lineItemGroups.items.sellableOffering.salesTaxes',
            'lineItemGroups.items.sellableOffering.salesDiscounts',
            'lineItemGroups.items.salesTaxes',
            'lineItemGroups.items.salesDiscounts',
            'lineItemGroups.items.offering',
            'lineItemGroups.children.offeringCategory',
            'lineItemGroups.children.items.sellableOffering.salesTaxes',
            'lineItemGroups.children.items.sellableOffering.salesDiscounts',
            'lineItemGroups.children.items.salesTaxes',
            'lineItemGroups.children.items.salesDiscounts',
            'lineItemGroups.children.items.purchaseTaxes',
            'lineItemGroups.children.items.purchaseDiscounts',
            'lineItemGroups.children.items.taxes',
            'lineItemGroups.children.items.discounts',
            'lineItemGroups.children.items.offering.salesTaxes',
            'lineItemGroups.children.items.offering.salesDiscounts',
            'lineItemGroups.children.items.offering',
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var VariationOrder $record */
        $lineItems = collect($data['lineItemGroups'] ?? []);

        $this->deleteRemovedLineItems($record, $lineItems);

        $this->handleLineItems($record, $lineItems);

        $totals = $this->updateDocumentTotals($record, $data);

        $data = array_merge($data, $totals);

        return parent::handleRecordUpdate($record, $data);
    }

    protected function afterSave(): void
    {
        $taxKey = $this->record::documentType()->getTaxKey();
        $taxIds = $this->data[$taxKey] ?? null;

        if ($taxIds !== null) {
            $this->record->{$taxKey}()->withoutGlobalScopes()->sync($taxIds);
        }
    }
}

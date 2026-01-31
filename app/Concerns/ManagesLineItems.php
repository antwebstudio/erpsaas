<?php

namespace App\Concerns;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ManagesLineItems
{
    protected function handleLineItems(Model $record, Collection $lineItems): void
    {
        // Check if we are handling groups or flat items
        $isGrouped = $lineItems->first(fn ($item) => isset($item['items']));

        if ($isGrouped) {
             $this->handleLineItemGroups($record, $lineItems);
             return;
        }

        foreach ($lineItems as $index => $itemData) {
            $lineItem = isset($itemData['id'])
                ? $record->lineItems->find($itemData['id'])
                : $record->lineItems()->make();

            $lineItem->fill([
                'offering_id' => $itemData['offering_id'],
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'unit' => $itemData['unit'] ?? null,
                'line_number' => $index + 1,
            ]);

            if (! $lineItem->exists) {
                $lineItem->documentable()->associate($record);
            }

            $lineItem->save();

            $this->handleLineItemAdjustments($lineItem, $itemData, $record->discount_method);
            $this->updateLineItemTotals($lineItem, $record->discount_method);
        }
    }

    protected function handleLineItemGroups(Model $record, Collection $groups): void
    {
        $groupOrder = 0;
        foreach ($groups as $groupId => $groupData) {
            $groupOrder++;
            $group = $record->lineItemGroups()->find($groupId);

            if (! $group && isset($groupData['id'])) {
                $group = $record->lineItemGroups()->find($groupData['id']);
            }

            if (! $group) {
                $group = $record->lineItemGroups()->make();
            }

            $group->fill([
                'name' => $groupData['name'] ?? null,
                'order' => $groupOrder,
            ]);

            $group->save();

            // Handle items within group
            $items = collect($groupData['items'] ?? []);
            
            // Delete removed items from this specific group
            // We need to be careful not to delete items that were just moved to another group
            // But since we are iterating groups, we might handle deletions globally in deleteRemovedLineItemGroups
            // Here we just handle updates/creates
            
            $itemIndex = 0;
            foreach ($items as $itemId => $itemData) {
                $itemIndex++;
                
                $lineItem = $record->lineItems()->find($itemId);

                if (! $lineItem && isset($itemData['id'])) {
                    $lineItem = $record->lineItems()->find($itemData['id']);
                }

                 if (! $lineItem) {
                     $lineItem = $record->lineItems()->make();
                 }

                 $lineItem->fill([
                     'group_id' => $group->id,
                     'offering_id' => $itemData['offering_id'],
                     'description' => $itemData['description'] ?? null,
                     'quantity' => $itemData['quantity'],
                     'unit_price' => $itemData['unit_price'],
                     'unit' => $itemData['unit'] ?? null,
                     'line_number' => $itemIndex,
                 ]);
                 
                 if (! $lineItem->exists) {
                    $lineItem->documentable()->associate($record);
                 }

                 $lineItem->save();

                 $this->handleLineItemAdjustments($lineItem, $itemData, $record->discount_method);
                 $this->updateLineItemTotals($lineItem, $record->discount_method);
            }
        }
    }

    protected function deleteRemovedLineItems(Model $record, Collection $lineItems): void
    {
        // Check for groups
        $isGrouped = $lineItems->first(fn ($item) => isset($item['items']));

        if ($isGrouped) {
             $this->deleteRemovedLineItemGroups($record, $lineItems);
        } else {
            $existingLineItemIds = $record->lineItems->pluck('id');
            $updatedLineItemIds = $lineItems->pluck('id')->filter();
            $lineItemsToDelete = $existingLineItemIds->diff($updatedLineItemIds);

            if ($lineItemsToDelete->isNotEmpty()) {
                $record
                    ->lineItems()
                    ->whereIn('id', $lineItemsToDelete)
                    ->each(fn (DocumentLineItem $lineItem) => $lineItem->delete());
            }
        }
    }

    protected function deleteRemovedLineItemGroups(Model $record, Collection $groups): void
    {
        // Delete removed groups
        $existingGroupIds = $record->lineItemGroups->pluck('id');
        
        $updatedGroupIds = $groups->keys()
            ->filter(fn ($id) => \Illuminate\Support\Str::isUuid($id))
            ->merge($groups->pluck('id'))
            ->filter();

        $groupsToDelete = $existingGroupIds->diff($updatedGroupIds);

        if ($groupsToDelete->isNotEmpty()) {
            $record->lineItemGroups()
                ->whereIn('id', $groupsToDelete)
                ->each(fn ($group) => $group->delete());
        }

        // Delete removed items from remaining groups
        $allUpdatedItemIds = $groups->pluck('items')
             ->flatMap(fn ($items) => 
                 collect($items)->keys()->filter(fn ($id) => \Illuminate\Support\Str::isUuid($id))
                 ->merge(collect($items)->pluck('id'))
             )
            ->filter();
            
        $existingItemIds = $record->lineItems()->pluck('id'); // Get ALL items for doc

        $itemsToDelete = $existingItemIds->diff($allUpdatedItemIds);

        if ($itemsToDelete->isNotEmpty()) {
            $record->lineItems()
                ->whereIn('id', $itemsToDelete)
                ->each(fn ($item) => $item->delete());
        }
    }

    protected function handleLineItemAdjustments(DocumentLineItem $lineItem, array $itemData, DocumentDiscountMethod $discountMethod): void
    {
        $isBill = $lineItem->documentable instanceof Bill;

        $taxType = $isBill ? 'purchaseTaxes' : 'salesTaxes';
        $discountType = $isBill ? 'purchaseDiscounts' : 'salesDiscounts';

        $adjustmentIds = collect($itemData[$taxType] ?? [])
            ->merge($discountMethod->isPerLineItem() ? ($itemData[$discountType] ?? []) : [])
            ->filter()
            ->unique();

        $lineItem->adjustments()->sync($adjustmentIds);
        $lineItem->refresh();
    }

    protected function updateLineItemTotals(DocumentLineItem $lineItem, DocumentDiscountMethod $discountMethod): void
    {
        $lineItem->updateQuietly([
            'tax_total' => $lineItem->calculateTaxTotalAmount(),
            'discount_total' => $discountMethod->isPerLineItem()
                ? $lineItem->calculateDiscountTotalAmount()
                : 0,
        ]);
    }

    protected function updateDocumentTotals(Model $record, array $data): array
    {
        $currencyCode = $data['currency_code'] ?? $record->currency_code ?? CurrencyAccessor::getDefaultCurrency();
        $subtotalCents = $record->lineItems()->sum('subtotal');
        $taxTotalCents = $record->lineItems()->sum('tax_total');
        $discountTotalCents = $this->calculateDiscountTotal(
            DocumentDiscountMethod::parse($data['discount_method']),
            AdjustmentComputation::parse($data['discount_computation']),
            $data['discount_rate'] ?? null,
            $subtotalCents,
            $record,
            $currencyCode,
        );

        $grandTotalCents = $subtotalCents + $taxTotalCents - $discountTotalCents;

        return [
            'subtotal' => $subtotalCents,
            'tax_total' => $taxTotalCents,
            'discount_total' => $discountTotalCents,
            'total' => $grandTotalCents,
        ];
    }

    protected function calculateDiscountTotal(
        DocumentDiscountMethod $discountMethod,
        ?AdjustmentComputation $discountComputation,
        ?string $discountRate,
        int $subtotalCents,
        Model $record,
        string $currencyCode
    ): int {
        if ($discountMethod->isPerLineItem()) {
            return $record->lineItems()->sum('discount_total');
        }

        if ($discountComputation?->isPercentage()) {
            $scaledRate = RateCalculator::parseLocalizedRate($discountRate);

            return RateCalculator::calculatePercentage($subtotalCents, $scaledRate);
        }

        return CurrencyConverter::convertToCents($discountRate, $currencyCode);
    }
}

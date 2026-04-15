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
        $isGrouped = $lineItems->contains(fn ($item) => isset($item['items']) || isset($item['children']));

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
                'is_locked' => $itemData['is_locked'] ?? 0,
                'line_number' => $index + 1,
            ]);

            if (! $lineItem->exists) {
                $lineItem->documentable()->associate($record);
            }

            $lineItem->save();

            $discountMethod = $record->discount_method ?? DocumentDiscountMethod::PerLineItem;
            $this->handleLineItemAdjustments($lineItem, $itemData, $discountMethod);
            $this->updateLineItemTotals($lineItem, $discountMethod);
        }
    }

    protected function handleLineItemGroups(Model $record, Collection $groups, ?int $parentId = null): void
    {
        $groupOrder = 0;
        foreach ($groups as $groupData) {
            $groupOrder++;
            
            $id = $groupData['id'] ?? null;
            $group = $id ? $record->lineItemGroups()->find($id) : null;

            if (! $group) {
                $group = $record->lineItemGroups()->make();
            }

            $group->fill([
                'parent_id' => $parentId,
                'offering_category_id' => $groupData['offering_category_id'] ?? null,
                'name' => $groupData['name'] ?? null,
                'order' => $groupOrder,
            ]);

            $group->save();

            // Handle items within group
            $items = collect($groupData['items'] ?? []);
            
            $itemIndex = 0;
            foreach ($items as $itemData) {
                $itemIndex++;
                
                $itemId = $itemData['id'] ?? null;
                $lineItem = $itemId ? $record->lineItems()->find($itemId) : null;

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
                     'is_locked' => $itemData['is_locked'] ?? 0,
                     'line_number' => $itemIndex,
                 ]);
                 
                 if (! $lineItem->exists) {
                    $lineItem->documentable()->associate($record);
                 }

                 $lineItem->save();

                 $discountMethod = $record->discount_method ?? DocumentDiscountMethod::PerLineItem;
                 $this->handleLineItemAdjustments($lineItem, $itemData, $discountMethod);
                 $this->updateLineItemTotals($lineItem, $discountMethod);
            }

            // Handle nested children groups
            if (isset($groupData['children'])) {
                $this->handleLineItemGroups($record, collect($groupData['children']), $group->id);
            }
        }
    }

    protected function deleteRemovedLineItems(Model $record, Collection $lineItems): void
    {
        // Check for groups
        $isGrouped = $lineItems->contains(fn ($item) => isset($item['items']) || isset($item['children']));

        if ($isGrouped) {
             $this->deleteRemovedLineItemGroups($record, $lineItems);
        } else {
            $existingLineItemIds = $record->lineItems()->pluck('id');
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
        $existingGroupIds = $record->lineItemGroups()->pluck('id');
        
        $updatedGroupIds = $this->getAllUpdatedGroupIds($groups);

        $groupsToDelete = $existingGroupIds->diff($updatedGroupIds);

        if ($groupsToDelete->isNotEmpty()) {
            $record->lineItemGroups()
                ->whereIn('id', $groupsToDelete)
                ->each(fn ($group) => $group->delete());
        }

        // Delete removed items from remaining groups
        $allUpdatedItemIds = $this->getAllUpdatedItemIds($groups);
            
        $existingItemIds = $record->lineItems()->pluck('id'); // Get ALL items for doc

        $itemsToDelete = $existingItemIds->diff($allUpdatedItemIds);

        if ($itemsToDelete->isNotEmpty()) {
            $record->lineItems()
                ->whereIn('id', $itemsToDelete)
                ->each(fn ($item) => $item->delete());
        }
    }

    protected function getAllUpdatedGroupIds(Collection $groups): array
    {
        $ids = [];

        foreach ($groups as $group) {
            if (isset($group['id'])) {
                $ids[] = $group['id'];
            }

            if (isset($group['children'])) {
                $ids = array_merge($ids, $this->getAllUpdatedGroupIds(collect($group['children'])));
            }
        }

        return array_filter($ids);
    }

    protected function getAllUpdatedItemIds(Collection $groups): array
    {
        $ids = [];

        foreach ($groups as $group) {
            $items = collect($group['items'] ?? []);
            $ids = array_merge($ids, $items->pluck('id')->filter()->toArray());

            if (isset($group['children'])) {
                $ids = array_merge($ids, $this->getAllUpdatedItemIds(collect($group['children'])));
            }
        }

        return array_filter($ids);
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

        $lineItem->adjustments()->withoutGlobalScopes()->sync($adjustmentIds);
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
        $taxKey = $record::documentType()->getTaxKey();
        $taxIds = $data[$taxKey] ?? null;

        // Automatically include default tax from template company if not already present
        $templateCompanyId = $data['template_company_id'] ?? $record->template_company_id ?? null;
        if ($templateCompanyId) {
            $defaultTaxId = \App\Models\Setting\CompanyProfile::withoutGlobalScopes()
                ->where('company_id', $templateCompanyId)
                ->value('default_sales_tax_id');
            if ($defaultTaxId) {
                // If taxIds is null, we get current taxes from the relationship
                $taxIds ??= $record->{$taxKey}()->withoutGlobalScopes()->pluck('adjustments.id')->toArray();
                if (! in_array($defaultTaxId, $taxIds)) {
                    $taxIds[] = (string) $defaultTaxId;
                }
            }
        }

        if ($taxIds !== null) {
            $record->{$taxKey}()->withoutGlobalScopes()->sync($taxIds);
        }

        if ($taxIds === null) {
            $taxIds = $record->{$taxKey}()->withoutGlobalScopes()->pluck('adjustments.id')->toArray();
        }

        $documentTaxTotalCents = $this->calculateDocumentTaxTotal($taxIds, $subtotalCents);

        $taxTotalCents = $record->lineItems()->sum('tax_total') + $documentTaxTotalCents;
        $discountTotalCents = $this->calculateDiscountTotal(
            DocumentDiscountMethod::parse($data['discount_method'] ?? $record->discount_method ?? DocumentDiscountMethod::PerLineItem),
            AdjustmentComputation::parse($data['discount_computation'] ?? $record->discount_computation ?? AdjustmentComputation::Fixed),
            $data['discount_rate'] ?? $record->discount_rate ?? null,
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

    protected function calculateDocumentTaxTotal(array|Collection $taxIds, int $subtotalCents): int
    {
        if (empty($taxIds)) {
            return 0;
        }

        $taxes = \App\Models\Accounting\Adjustment::withoutGlobalScopes()->whereIn('id', $taxIds)->get();

        return $taxes->reduce(function (int $carry, \App\Models\Accounting\Adjustment $tax) use ($subtotalCents) {
            if ($tax->computation->isPercentage()) {
                return $carry + RateCalculator::calculatePercentage($subtotalCents, $tax->getRawOriginal('rate'));
            } else {
                return $carry + $tax->getRawOriginal('rate');
            }
        }, 0);
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

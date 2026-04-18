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
        $isGrouped = $lineItems->contains(fn ($item) => isset($item['items']) || isset($item['children']) || array_key_exists('name', $item));

        if ($isGrouped) {
             $this->handleLineItemGroups($record, $lineItems);
             return;
        }

        foreach ($lineItems as $index => $itemData) {
            // Skip ghost/empty items left behind by Livewire state after deletion
            if (! isset($itemData['quantity']) && ! filled($itemData['description'] ?? null)) {
                continue;
            }

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
            $hasName = filled($groupData['name'] ?? null);
            $hasCategory = filled($groupData['offering_category_id'] ?? null);

            // Count only real (non-ghost) items
            $realItemCount = 0;
            foreach ($groupData['items'] ?? [] as $itemData) {
                if (isset($itemData['quantity']) || filled($itemData['description'] ?? null)) {
                    $realItemCount++;
                }
            }
            $hasItems = $realItemCount > 0;
            $hasChildren = count($groupData['children'] ?? []) > 0;
            
            if (!$hasName && !$hasCategory && !$hasItems && !$hasChildren) {
                continue;
            }

            // For subgroups (child groups), skip and delete if they have no real items
            // and no children — they are empty subgroups that should not persist
            if ($parentId !== null && !$hasItems && !$hasChildren) {
                $id = $groupData['id'] ?? null;
                if ($id) {
                    $existingGroup = $record->lineItemGroups()->find($id);
                    if ($existingGroup) {
                        $existingGroup->items()->delete();
                        $existingGroup->delete();
                    }
                }
                continue;
            }

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
                // Skip ghost/empty items left behind by Livewire state after deletion
                if (! isset($itemData['quantity']) && ! filled($itemData['description'] ?? null)) {
                    continue;
                }

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
        if ($lineItems->isEmpty()) {
            $record->lineItems()->each(fn ($item) => $item->delete());
            $record->lineItemGroups()->each(fn ($group) => $group->delete());
            return;
        }

        // Check for groups
        $isGrouped = $lineItems->contains(fn ($item) => isset($item['items']) || isset($item['children']) || array_key_exists('name', $item));

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

        \Illuminate\Support\Facades\Log::info('ManagesLineItems: Deleting groups', [
            'existing' => $existingGroupIds->toArray(),
            'updated' => $updatedGroupIds,
            'to_delete' => $groupsToDelete->toArray(),
            'payload' => $groups->toArray(),
        ]);

        if ($groupsToDelete->isNotEmpty()) {
            $record->lineItemGroups()
                ->whereIn('id', $groupsToDelete)
                ->each(function ($group) {
                    // Delete items first (group_id FK uses nullOnDelete, so items
                    // would become orphaned rather than cascade-deleted)
                    $group->items()->delete();
                    // Delete any child groups (parent_id FK uses nullOnDelete)
                    $group->children()->each(function ($child) {
                        $child->items()->delete();
                        $child->delete();
                    });
                    $group->delete();
                });
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

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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait ManagesLineItems
{
    private array $preloadedGroups = [];

    private array $preloadedItems = [];

    /** Existing items (have IDs) collected for a single batch upsert. */
    private array $pendingExistingItems = [];

    /** New items (no IDs) to be individually inserted to get auto-increment IDs. */
    private array $pendingNewItems = [];

    /** Adjustment IDs keyed by line item ID, flushed in bulk after all items are saved. */
    private array $pendingAdjustments = [];

    protected function handleLineItems(Model $record, Collection $lineItems): void
    {
        $isGrouped = $lineItems->contains(fn ($item) => isset($item['items']) || isset($item['children']) || array_key_exists('name', $item));

        $this->pendingAdjustments = [];

        if ($isGrouped) {
            $this->pendingExistingItems = [];
            $this->pendingNewItems = [];
            $this->handleLineItemGroups($record, $lineItems);
            $this->flushPendingItems();
            $this->flushPendingAdjustments();
            $this->flushPendingTotals($record->discount_method ?? DocumentDiscountMethod::PerLineItem);

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
                'kiv' => $itemData['kiv'] ?? false,
                'line_number' => $index + 1,
            ]);

            if (! $lineItem->exists) {
                $lineItem->documentable()->associate($record);
            }

            $lineItem->save();

            $discountMethod = $record->discount_method ?? DocumentDiscountMethod::PerLineItem;
            $this->collectItemAdjustments($lineItem->id, $itemData, $discountMethod, $record);
        }

        $this->flushPendingAdjustments();
        $this->flushPendingTotals($record->discount_method ?? DocumentDiscountMethod::PerLineItem);
    }

    protected function handleLineItemGroups(Model $record, Collection $groups, ?int $parentId = null): void
    {
        if ($parentId === null) {
            $this->preloadedGroups = $record->lineItemGroups()->withoutGlobalScopes()->get()->keyBy('id')->all();
            $this->preloadedItems = $record->lineItems()->withoutGlobalScopes()->get()->keyBy('id')->all();
        }

        $discountMethod = $record->discount_method ?? DocumentDiscountMethod::PerLineItem;
        $isBill = $record->getMorphClass() === (new Bill)->getMorphClass();

        $groupOrder = 0;
        foreach ($groups as $groupData) {
            $hasName = filled($groupData['name'] ?? null);
            $hasCategory = filled($groupData['offering_category_id'] ?? null);

            $realItemCount = 0;
            foreach ($groupData['items'] ?? [] as $itemData) {
                if (isset($itemData['quantity']) || filled($itemData['description'] ?? null)) {
                    $realItemCount++;
                }
            }
            $hasItems = $realItemCount > 0;
            $hasChildren = count($groupData['children'] ?? []) > 0;

            if (! $hasName && ! $hasCategory && ! $hasItems && ! $hasChildren) {
                continue;
            }

            if ($parentId !== null && ! $hasItems && ! $hasChildren) {
                $id = $groupData['id'] ?? null;
                if ($id) {
                    $existingGroup = $this->preloadedGroups[$id] ?? null;
                    if ($existingGroup) {
                        $existingGroup->items()->delete();
                        $existingGroup->delete();
                    }
                }

                continue;
            }

            $groupOrder++;

            $id = $groupData['id'] ?? null;
            $group = $id ? ($this->preloadedGroups[$id] ?? null) : null;

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

            $itemIndex = 0;
            foreach ($groupData['items'] ?? [] as $itemData) {
                if (! isset($itemData['quantity']) && ! filled($itemData['description'] ?? null)) {
                    continue;
                }

                $itemIndex++;

                $taxType = $isBill ? 'purchaseTaxes' : 'salesTaxes';
                $discountType = $isBill ? 'purchaseDiscounts' : 'salesDiscounts';
                $adjustmentIds = collect($itemData[$taxType] ?? [])
                    ->merge($discountMethod->isPerLineItem() ? ($itemData[$discountType] ?? []) : [])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $itemId = $itemData['id'] ?? null;

                if ($itemId) {
                    $this->pendingExistingItems[] = [
                        'id' => $itemId,
                        // Required NOT NULL columns without DB defaults — needed by the
                        // INSERT side of upsert's INSERT...ON DUPLICATE KEY UPDATE.
                        'company_id' => $record->company_id,
                        'documentable_id' => $record->id,
                        'documentable_type' => $record->getMorphClass(),
                        'group_id' => $group->id,
                        'offering_id' => $itemData['offering_id'] ?? null,
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $this->resolveUnitPrice($itemData['unit_price'] ?? 0),
                        'unit' => $itemData['unit'] ?? null,
                        'is_locked' => $itemData['is_locked'] ?? 0,
                        'kiv' => $itemData['kiv'] ?? false,
                        'line_number' => $itemIndex,
                    ];
                    $this->pendingAdjustments[$itemId] = $adjustmentIds;
                } else {
                    $this->pendingNewItems[] = [
                        'record' => $record,
                        'group_id' => $group->id,
                        'item_data' => $itemData,
                        'line_number' => $itemIndex,
                        'adjustment_ids' => $adjustmentIds,
                    ];
                }
            }

            if (isset($groupData['children'])) {
                $this->handleLineItemGroups($record, collect($groupData['children']), $group->id);
            }
        }
    }

    /**
     * Persist all collected items: one upsert for existing, individual inserts for new.
     */
    protected function flushPendingItems(): void
    {
        $now = now();
        $userId = Auth::id();

        if ($this->pendingExistingItems) {
            $toUpsert = array_map(fn ($item) => array_merge($item, [
                'updated_by' => $userId,
                'updated_at' => $now,
            ]), $this->pendingExistingItems);

            DocumentLineItem::upsert(
                $toUpsert,
                ['id'],
                ['group_id', 'offering_id', 'description', 'quantity', 'unit_price', 'unit', 'is_locked', 'kiv', 'line_number', 'updated_by', 'updated_at']
            );
        }

        foreach ($this->pendingNewItems as $pending) {
            $lineItem = $pending['record']->lineItems()->make();
            $lineItem->fill([
                'group_id' => $pending['group_id'],
                'offering_id' => $pending['item_data']['offering_id'] ?? null,
                'description' => $pending['item_data']['description'] ?? null,
                'quantity' => $pending['item_data']['quantity'],
                'unit_price' => $this->resolveUnitPrice($pending['item_data']['unit_price'] ?? 0),
                'unit' => $pending['item_data']['unit'] ?? null,
                'is_locked' => $pending['item_data']['is_locked'] ?? 0,
                'kiv' => $pending['item_data']['kiv'] ?? false,
                'line_number' => $pending['line_number'],
            ]);
            $lineItem->documentable()->associate($pending['record']);
            $lineItem->save();
            $this->pendingAdjustments[$lineItem->id] = $pending['adjustment_ids'];
        }
    }

    protected function deleteRemovedLineItems(Model $record, Collection $lineItems): void
    {
        if ($lineItems->isEmpty()) {
            $record->lineItems()->each(fn ($item) => $item->delete());
            $record->lineItemGroups()->each(fn ($group) => $group->delete());

            return;
        }

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
        $existingGroupIds = $record->lineItemGroups()->pluck('id');
        $updatedGroupIds = $this->getAllUpdatedGroupIds($groups);
        $groupsToDelete = $existingGroupIds->diff($updatedGroupIds);

        if ($groupsToDelete->isNotEmpty()) {
            $record->lineItemGroups()
                ->whereIn('id', $groupsToDelete)
                ->each(function ($group) {
                    $group->items()->delete();
                    $group->children()->each(function ($child) {
                        $child->items()->delete();
                        $child->delete();
                    });
                    $group->delete();
                });
        }

        $allUpdatedItemIds = $this->getAllUpdatedItemIds($groups);
        $existingItemIds = $record->lineItems()->pluck('id');
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

    /**
     * Collect adjustment IDs for an item (used by the non-grouped path).
     */
    protected function collectItemAdjustments(int $itemId, array $itemData, DocumentDiscountMethod $discountMethod, Model $record): void
    {
        $isBill = $record->getMorphClass() === (new Bill)->getMorphClass();
        $taxType = $isBill ? 'purchaseTaxes' : 'salesTaxes';
        $discountType = $isBill ? 'purchaseDiscounts' : 'salesDiscounts';

        $adjustmentIds = collect($itemData[$taxType] ?? [])
            ->merge($discountMethod->isPerLineItem() ? ($itemData[$discountType] ?? []) : [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->pendingAdjustments[$itemId] = $adjustmentIds;
    }

    /**
     * Sync all collected item adjustments in two queries instead of 3N queries.
     */
    protected function flushPendingAdjustments(): void
    {
        if (empty($this->pendingAdjustments)) {
            return;
        }

        $morphType = (new DocumentLineItem)->getMorphClass();
        $itemIds = array_keys($this->pendingAdjustments);

        DB::table('adjustmentables')
            ->whereIn('adjustmentable_id', $itemIds)
            ->where('adjustmentable_type', $morphType)
            ->delete();

        $toInsert = [];
        foreach ($this->pendingAdjustments as $itemId => $adjustmentIds) {
            foreach ($adjustmentIds as $adjustmentId) {
                $toInsert[] = [
                    'adjustmentable_id' => $itemId,
                    'adjustmentable_type' => $morphType,
                    'adjustment_id' => (int) $adjustmentId,
                ];
            }
        }

        if ($toInsert) {
            DB::table('adjustmentables')->insert($toInsert);
        }
    }

    /**
     * Compute and persist tax_total / discount_total for all pending items in two queries.
     */
    protected function flushPendingTotals(DocumentDiscountMethod $discountMethod): void
    {
        if (empty($this->pendingAdjustments)) {
            return;
        }

        $itemIds = array_keys($this->pendingAdjustments);

        $items = DocumentLineItem::whereIn('id', $itemIds)
            ->with(['taxes', 'discounts'])
            ->get();

        $now = now();
        $toUpdate = [];
        foreach ($items as $item) {
            $toUpdate[] = [
                'id' => $item->id,
                // Required NOT NULL columns without DB defaults.
                'company_id' => $item->company_id,
                'documentable_id' => $item->documentable_id,
                'documentable_type' => $item->documentable_type,
                'tax_total' => $item->calculateTaxTotalAmount(),
                'discount_total' => $discountMethod->isPerLineItem()
                    ? $item->calculateDiscountTotalAmount()
                    : 0,
                'updated_at' => $now,
            ];
        }

        if ($toUpdate) {
            DocumentLineItem::upsert($toUpdate, ['id'], ['tax_total', 'discount_total', 'updated_at']);
        }
    }

    protected function updateDocumentTotals(Model $record, array $data): array
    {
        $currencyCode = $data['currency_code'] ?? $record->currency_code ?? CurrencyAccessor::getDefaultCurrency();
        $subtotalCents = $record->lineItems()->where('kiv', false)->sum('subtotal');
        $taxKey = $record::documentType()->getTaxKey();
        $taxIds = $data[$taxKey] ?? null;

        $templateCompanyId = $data['template_company_id'] ?? $record->template_company_id ?? null;
        if ($templateCompanyId) {
            $defaultTaxId = \App\Models\Setting\CompanyProfile::withoutGlobalScopes()
                ->where('company_id', $templateCompanyId)
                ->value('default_sales_tax_id');
            if ($defaultTaxId) {
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

        $taxTotalCents = $record->lineItems()->where('kiv', false)->sum('tax_total') + $documentTaxTotalCents;
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

    protected function calculateDocumentTaxTotal(array | Collection $taxIds, int $subtotalCents): int
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
            return $record->lineItems()->where('kiv', false)->sum('discount_total');
        }

        if ($discountComputation?->isPercentage()) {
            $scaledRate = RateCalculator::parseLocalizedRate($discountRate);

            return RateCalculator::calculatePercentage($subtotalCents, $scaledRate);
        }

        return CurrencyConverter::convertToCents($discountRate, $currencyCode);
    }

    /**
     * The money field's dehydrateStateUsing converts display values to cents (int).
     * However, items added programmatically (e.g. via direct $this->data manipulation)
     * go through afterStateHydrated which re-interprets the display string as cents,
     * leaving the value as a non-integer display string that still needs conversion.
     */
    protected function resolveUnitPrice(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return CurrencyConverter::convertToCents($value);
    }
}

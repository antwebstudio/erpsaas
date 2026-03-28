<?php

namespace App\View\Models;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Models\Accounting\Adjustment;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Illuminate\Support\Number;

class DocumentTotalViewModel
{
    public function __construct(
        public ?array $data,
        public DocumentType $documentType = DocumentType::Invoice,
    ) {}

    public function buildViewData(): array
    {
        $currencyCode = $this->data['currency_code'] ?? CurrencyAccessor::getDefaultCurrency();
        $defaultCurrencyCode = CurrencyAccessor::getDefaultCurrency();

        $lineItems = collect($this->data['lineItems'] ?? []);

        if (isset($this->data['lineItemGroups'])) {
            $items = collect();
            foreach ($this->data['lineItemGroups'] as $group) {
                if (isset($group['items'])) {
                    $items = $items->concat(array_values($group['items']));
                }
                if (isset($group['children'])) {
                    foreach ($group['children'] as $child) {
                        if (isset($child['items'])) {
                            $items = $items->concat(array_values($child['items']));
                        }
                    }
                }
            }
            $lineItems = $items;
        }

        $subtotalInCents = $lineItems->sum(fn ($item) => $this->calculateLineSubtotalInCents($item, $currencyCode));
        
        $lineTaxTotalInCents = $this->calculateAdjustmentsTotalInCents($lineItems, $this->documentType->getTaxKey(), $currencyCode);
        $documentTaxKey = $this->documentType->getTaxKey();
        $documentTaxIds = $this->data[$documentTaxKey] ?? [];
        $documentTaxTotalInCents = $this->calculateDocumentTaxTotalInCents($documentTaxIds, $subtotalInCents);
        
        $taxTotalInCents = $lineTaxTotalInCents + $documentTaxTotalInCents;
        
        $discountTotalInCents = $this->calculateDiscountTotalInCents($lineItems, $subtotalInCents, $currencyCode);

        $grandTotalInCents = $subtotalInCents + ($taxTotalInCents - $discountTotalInCents);

        $amountDueInCents = $this->calculateAmountDueInCents($grandTotalInCents, $currencyCode);

        $conversionMessage = $this->buildConversionMessage($grandTotalInCents, $currencyCode, $defaultCurrencyCode);

        $discountMethod = DocumentDiscountMethod::parse($this->data['discount_method']);
        $isPerDocumentDiscount = $discountMethod->isPerDocument();

        $taxTotal = $taxTotalInCents > 0
            ? CurrencyConverter::formatCentsToMoney($taxTotalInCents, $currencyCode)
            : null;

        $discountTotal = ($isPerDocumentDiscount || $discountTotalInCents > 0)
            ? CurrencyConverter::formatCentsToMoney($discountTotalInCents, $currencyCode)
            : null;

        $subtotal = ($taxTotal || $discountTotal)
            ? CurrencyConverter::formatCentsToMoney($subtotalInCents, $currencyCode)
            : null;

        $grandTotal = CurrencyConverter::formatCentsToMoney($grandTotalInCents, $currencyCode);

        $amountDue = $this->documentType !== DocumentType::Estimate
            ? CurrencyConverter::formatCentsToMoney($amountDueInCents, $currencyCode)
            : null;

        return [
            'subtotal' => $subtotal,
            'taxTotal' => $taxTotal,
            'discountTotal' => $discountTotal,
            'grandTotal' => $grandTotal,
            'amountDue' => $amountDue,
            'currencyCode' => $currencyCode,
            'conversionMessage' => $conversionMessage,
            'isPerDocumentDiscount' => $isPerDocumentDiscount,
        ];
    }

    private function calculateLineSubtotalInCents(array $item, string $currencyCode): int
    {
        $quantity = max((float) ($item['quantity'] ?? 0), 0);
        $unitPrice = CurrencyConverter::isValidAmount($item['unit_price'], 'USD')
            ? CurrencyConverter::convertToFloat($item['unit_price'], 'USD')
            : 0;

        $subtotal = $quantity * $unitPrice;

        return CurrencyConverter::convertToCents($subtotal, 'USD');
    }

    private function calculateAdjustmentsTotalInCents($lineItems, string $key, string $currencyCode): int
    {
        // Batch-load all adjustment IDs across all line items in a single query
        $allAdjustmentIds = $lineItems->pluck($key)->flatten()->filter()->unique()->values()->all();
        $adjustmentCache = Adjustment::whereIn('id', $allAdjustmentIds)->get()->keyBy('id');

        return $lineItems->reduce(function ($carry, $item) use ($key, $adjustmentCache) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = CurrencyConverter::isValidAmount($item['unit_price'], 'USD')
                ? CurrencyConverter::convertToFloat($item['unit_price'], 'USD')
                : 0;

            $adjustmentIds = $item[$key] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $lineTotalInCents = CurrencyConverter::convertToCents($lineTotal, 'USD');

            $adjustmentTotal = collect($adjustmentIds)
                ->sum(function ($id) use ($adjustmentCache, $lineTotalInCents) {
                    $adjustment = $adjustmentCache->get($id);
                    if (! $adjustment) return 0;
                    if ($adjustment->computation->isPercentage()) {
                        return RateCalculator::calculatePercentage($lineTotalInCents, $adjustment->getRawOriginal('rate'));
                    } else {
                        return $adjustment->getRawOriginal('rate');
                    }
                });

            return $carry + $adjustmentTotal;
        }, 0);
    }

    private function calculateDiscountTotalInCents($lineItems, int $subtotalInCents, string $currencyCode): int
    {
        $discountMethod = DocumentDiscountMethod::parse($this->data['discount_method']) ?? DocumentDiscountMethod::PerLineItem;

        if ($discountMethod->isPerLineItem()) {
            return $this->calculateAdjustmentsTotalInCents($lineItems, $this->documentType->getDiscountKey(), $currencyCode);
        }

        $discountComputation = AdjustmentComputation::parse($this->data['discount_computation']) ?? AdjustmentComputation::Percentage;
        $discountRate = blank($this->data['discount_rate']) ? '0' : $this->data['discount_rate'];

        if ($discountComputation->isPercentage()) {
            $scaledDiscountRate = RateCalculator::parseLocalizedRate($discountRate);

            return RateCalculator::calculatePercentage($subtotalInCents, $scaledDiscountRate);
        }

        if (! CurrencyConverter::isValidAmount($discountRate, $currencyCode)) {
            $discountRate = '0';
        }

        return CurrencyConverter::convertToCents($discountRate, $currencyCode);
    }

    private function calculateDocumentTaxTotalInCents(array $taxIds, int $subtotalInCents): int
    {
        if (empty($taxIds)) {
            return 0;
        }

        $taxes = Adjustment::whereIn('id', $taxIds)->get();

        return $taxes->reduce(function (int $carry, Adjustment $tax) use ($subtotalInCents) {
            if ($tax->computation->isPercentage()) {
                return $carry + RateCalculator::calculatePercentage($subtotalInCents, $tax->getRawOriginal('rate'));
            } else {
                return $carry + $tax->getRawOriginal('rate');
            }
        }, 0);
    }

    private function buildConversionMessage(int $grandTotalInCents, string $currencyCode, string $defaultCurrencyCode): ?string
    {
        if ($currencyCode === $defaultCurrencyCode) {
            return null;
        }

        $rate = currency($currencyCode)->getRate();
        $indirectRate = 1 / $rate;

        $convertedTotalInCents = CurrencyConverter::convertBalance($grandTotalInCents, $currencyCode, $defaultCurrencyCode);

        $formattedRate = Number::format($indirectRate, maxPrecision: 10);

        return sprintf(
            'Currency conversion: %s (%s) at an exchange rate of 1 %s = %s %s',
            CurrencyConverter::formatCentsToMoney($convertedTotalInCents, $defaultCurrencyCode),
            $defaultCurrencyCode,
            $currencyCode,
            $formattedRate,
            $defaultCurrencyCode
        );
    }

    private function calculateAmountDueInCents(int $grandTotalInCents, string $currencyCode): int
    {
        $amountPaidInCents = $this->data['amount_paid'] ?? 0;

        return $grandTotalInCents - $amountPaidInCents;
    }
}

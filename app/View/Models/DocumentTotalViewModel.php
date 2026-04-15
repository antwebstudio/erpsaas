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

        $lineItems = $this->data['lineItems'] ?? [];

        if (isset($this->data['lineItemGroups']) && is_array($this->data['lineItemGroups'])) {
            $flattenedItems = [];
            foreach ($this->data['lineItemGroups'] as $group) {
                if (isset($group['items']) && is_array($group['items'])) {
                    foreach ($group['items'] as $item) {
                        $flattenedItems[] = $item;
                    }
                }
                if (isset($group['children']) && is_array($group['children'])) {
                    foreach ($group['children'] as $child) {
                        if (isset($child['items']) && is_array($child['items'])) {
                            foreach ($child['items'] as $item) {
                                $flattenedItems[] = $item;
                            }
                        }
                    }
                }
            }
            $lineItems = $flattenedItems;
        }

        $taxKey = $this->documentType->getTaxKey();
        $discountKey = $this->documentType->getDiscountKey();
        $documentTaxIds = $this->data[$taxKey] ?? [];

        // Batch-load all adjustment IDs across all line items and document in a single query
        $allAdjustmentIds = [];
        foreach ($lineItems as $item) {
            $lineTaxIds = (array) ($item[$taxKey] ?? []);
            foreach ($lineTaxIds as $id) $allAdjustmentIds[$id] = $id;

            $lineDiscountIds = (array) ($item[$discountKey] ?? []);
            foreach ($lineDiscountIds as $id) $allAdjustmentIds[$id] = $id;
        }
        foreach ($documentTaxIds as $id) $allAdjustmentIds[$id] = $id;

        $adjustmentCache = empty($allAdjustmentIds) 
            ? collect() 
            : Adjustment::withoutGlobalScopes()->whereIn('id', array_keys($allAdjustmentIds))->get()->keyBy('id');

        $subtotalInCents = 0;
        $lineTaxTotalInCents = 0;
        $lineDiscountTotalInCents = 0;

        foreach ($lineItems as $item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = CurrencyConverter::isValidAmount($item['unit_price'], 'USD')
                ? CurrencyConverter::convertToFloat($item['unit_price'], 'USD')
                : 0;

            $lineSubtotal = $quantity * $unitPrice;
            $lineSubtotalInCents = CurrencyConverter::convertToCents($lineSubtotal, 'USD');
            $subtotalInCents += $lineSubtotalInCents;

            // Line Taxes
            $lineTaxIds = $item[$taxKey] ?? [];
            foreach ($lineTaxIds as $id) {
                $adjustment = $adjustmentCache->get($id);
                if ($adjustment) {
                    if ($adjustment->computation->isPercentage()) {
                        $lineTaxTotalInCents += RateCalculator::calculatePercentage($lineSubtotalInCents, $adjustment->getRawOriginal('rate'));
                    } else {
                        $lineTaxTotalInCents += $adjustment->getRawOriginal('rate');
                    }
                }
            }

            // Line Discounts
            $lineDiscountIds = $item[$discountKey] ?? [];
            foreach ($lineDiscountIds as $id) {
                $adjustment = $adjustmentCache->get($id);
                if ($adjustment) {
                    if ($adjustment->computation->isPercentage()) {
                        $lineDiscountTotalInCents += RateCalculator::calculatePercentage($lineSubtotalInCents, $adjustment->getRawOriginal('rate'));
                    } else {
                        $lineDiscountTotalInCents += $adjustment->getRawOriginal('rate');
                    }
                }
            }
        }

        $documentTaxTotalInCents = 0;
        foreach ($documentTaxIds as $id) {
            $adjustment = $adjustmentCache->get($id);
            if ($adjustment) {
                if ($adjustment->computation->isPercentage()) {
                    $documentTaxTotalInCents += RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                } else {
                    $documentTaxTotalInCents += $adjustment->getRawOriginal('rate');
                }
            }
        }

        $taxTotalInCents = $lineTaxTotalInCents + $documentTaxTotalInCents;

        $discountMethod = DocumentDiscountMethod::parse($this->data['discount_method']) ?? DocumentDiscountMethod::PerLineItem;
        if ($discountMethod->isPerLineItem()) {
            $discountTotalInCents = $lineDiscountTotalInCents;
        } else {
            $discountComputation = AdjustmentComputation::parse($this->data['discount_computation']) ?? AdjustmentComputation::Percentage;
            $discountRate = blank($this->data['discount_rate']) ? '0' : $this->data['discount_rate'];

            if ($discountComputation->isPercentage()) {
                $scaledDiscountRate = RateCalculator::parseLocalizedRate($discountRate);
                $discountTotalInCents = RateCalculator::calculatePercentage($subtotalInCents, $scaledDiscountRate);
            } else {
                if (! CurrencyConverter::isValidAmount($discountRate, $currencyCode)) {
                    $discountRate = '0';
                }
                $discountTotalInCents = CurrencyConverter::convertToCents($discountRate, $currencyCode);
            }
        }

        $grandTotalInCents = $subtotalInCents + ($taxTotalInCents - $discountTotalInCents);

        $amountDueInCents = $this->calculateAmountDueInCents($grandTotalInCents, $currencyCode);

        $conversionMessage = $this->buildConversionMessage($grandTotalInCents, $currencyCode, $defaultCurrencyCode);

        $discountMethod = DocumentDiscountMethod::parse($this->data['discount_method']);
        $isPerDocumentDiscount = $discountMethod->isPerDocument();

        $taxTotal = ($taxTotalInCents !== 0)
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

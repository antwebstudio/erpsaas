<?php

namespace App\DTO;

use App\Models\Accounting\DocumentLineItem;
use App\Utilities\Currency\CurrencyConverter;

readonly class LineItemDTO
{
    public function __construct(
        public string $name,
        public string $description,
        public float | int $quantity,
        public string $unitPrice,
        public string $subtotal,
        public ?string $unit,
        public bool $isLocked,
        public ?int $offeringId = null,
        public bool $isNegative = false,
    ) {}

    public static function fromModel(DocumentLineItem $lineItem): self
    {
        $currencyCode = $lineItem->documentable?->currency_code;

        return new self(
            name: $lineItem->offering->name ?? '',
            description: $lineItem->description ?? '',
            quantity: (float) $lineItem->quantity,
            unitPrice: $lineItem->kiv ? 'KIV' : self::formatToMoney($lineItem->unit_price, $currencyCode, true),
            subtotal: $lineItem->kiv ? 'KIV' : self::formatToMoney($lineItem->subtotal, $currencyCode, true),
            unit: $lineItem->unit,
            isLocked: $lineItem->is_locked ?? false,
            offeringId: $lineItem->offering_id,
            isNegative: (int) $lineItem->getRawOriginal('unit_price') < 0,
        );
    }

    protected static function formatToMoney(float | string | int $value, ?string $currencyCode, bool $allowFoc = false): string
    {
        if ($allowFoc && (float) $value == 0) {
            return 'FOC';
        }

        if (is_int($value)) {
            return CurrencyConverter::formatCentsToMoney($value, $currencyCode);
        }

        return CurrencyConverter::formatToMoney($value, $currencyCode);
    }
}

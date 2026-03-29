<?php

namespace App\DTO;

use App\Models\Accounting\DocumentLineItem;
use App\Utilities\Currency\CurrencyConverter;

readonly class LineItemDTO
{
    public function __construct(
        public string $name,
        public string $description,
        public int $quantity,
        public string $unitPrice,
        public string $subtotal,
        public ?string $unit,
        public bool $isLocked,
    ) {}

    public static function fromModel(DocumentLineItem $lineItem): self
    {
        return new self(
            name: $lineItem->offering->name ?? '',
            description: $lineItem->description ?? '',
            quantity: $lineItem->quantity,
            unitPrice: self::formatToMoney($lineItem->unit_price, $lineItem->documentable?->currency_code),
            subtotal: self::formatToMoney($lineItem->subtotal, $lineItem->documentable?->currency_code),
            unit: $lineItem->unit,
            isLocked: $lineItem->is_locked ?? false,
        );
    }

    protected static function formatToMoney(float | string | int $value, ?string $currencyCode): string
    {
        if (is_int($value)) {
            return CurrencyConverter::formatCentsToMoney($value, $currencyCode);
        }

        return CurrencyConverter::formatToMoney($value, $currencyCode);
    }
}

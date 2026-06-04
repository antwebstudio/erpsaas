<?php

namespace App\DTO;

use App\Models\Accounting\DocumentLineItemGroup;

readonly class LineItemGroupDTO
{
    /**
     * @param  LineItemDTO[]  $items
     */
    public function __construct(
        public ?string $name,
        public iterable $items,
        public bool $isMain = true,
    ) {}

    public static function fromModel(DocumentLineItemGroup $group, bool $isMain = true): self
    {
        return new self(
            name: $group->name,
            items: $group->items->map(fn ($item) => LineItemDTO::fromModel($item)),
            isMain: $isMain,
        );
    }
}

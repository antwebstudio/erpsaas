<?php

namespace App\DTO;

use App\Models\Accounting\DocumentLineItemGroup;
use Illuminate\Support\Collection;

readonly class LineItemGroupDTO
{
    /**
     * @param  LineItemDTO[]  $items
     */
    public function __construct(
        public ?string $name,
        public iterable $items,
    ) {}

    public static function fromModel(DocumentLineItemGroup $group): self
    {
        return new self(
            name: $group->name,
            items: $group->items->map(fn ($item) => LineItemDTO::fromModel($item)),
        );
    }
}

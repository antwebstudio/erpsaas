<?php

namespace Database\Factories\Setting;

use App\Enums\Accounting\DocumentType;
use App\Enums\Setting\Font;
use App\Enums\Setting\Template;
use App\Models\Setting\DocumentDefault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentDefault>
 */
class DocumentDefaultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = DocumentDefault::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'payment_terms' => 'due_upon_receipt',
        ];
    }

    private const COLOR_PALETTE_BROWN = [
        'accent_color'          => '#C7B098',
        'color_text'            => '#293834',
        'color_secondary'       => '#d8d1c6',
        'color_secondary_text'  => '#293834',
        'color_section_bg'      => '#e62424ff',
        'color_section_bg_text' => '#293834',
        'color_group_bg'        => '#e8e2db', 
        'color_group_bg_text'   => '#293834',
        'color_subgroup_bg'     => '#ffffff',
        'color_subgroup_text'   => '#C7B098',
    ];

    private const COLOR_PALETTE_INDIGO = [
        'accent_color'          => '#C7B098',
    ];

    /**
     * The model's common default state.
     */
    private function baseState(DocumentType $type): array
    {
        $state = [
            'type' => $type,
            'number_prefix' => $type->getDefaultPrefix(),
            'item_name' => ['option' => 'items', 'custom' => null],
            'unit_name' => ['option' => 'quantity', 'custom' => null],
            'price_name' => ['option' => 'price', 'custom' => null],
            'amount_name' => ['option' => 'amount', 'custom' => null],
        ];

        if ($type !== DocumentType::Bill) {
            $colors = match ($type) {
                DocumentType::Estimate, DocumentType::VariationOrder => self::COLOR_PALETTE_BROWN,
                default => self::COLOR_PALETTE_INDIGO,
            };

            $state = [...$state,
                'header' => $type->getLabel(),
                'show_logo' => false,
                'font' => Font::Inter,
                'template' => Template::Default,
                ...$colors,
            ];
        }

        return $state;
    }

    /**
     * Indicate that the model's type is invoice.
     */
    public function invoice(): self
    {
        return $this->state($this->baseState(DocumentType::Invoice));
    }

    /**
     * Indicate that the model's type is bill.
     */
    public function bill(): self
    {
        return $this->state($this->baseState(DocumentType::Bill));
    }

    /**
     * Indicate that the model's type is estimate.
     */
    public function estimate(): self
    {
        return $this->state($this->baseState(DocumentType::Estimate));
    }

    /**
     * Indicate that the model's type is variation order.
     */
    public function variationOrder(): self
    {
        return $this->state($this->baseState(DocumentType::VariationOrder));
    }

    /**
     * Indicate that the model's type is contract.
     */
    public function contract(): self
    {
        return $this->state($this->baseState(DocumentType::Contract));
    }
}

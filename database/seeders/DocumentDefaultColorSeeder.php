<?php

namespace Database\Seeders;

use App\Enums\Accounting\DocumentType;
use App\Models\Setting\DocumentDefault;
use Illuminate\Database\Seeder;

class DocumentDefaultColorSeeder extends Seeder
{
    public function run(): void
    {
        $colorsByType = [
            DocumentType::Invoice->value => [
                'accent_color'          => '#4F46E5',
                'color_text'            => null,
                'color_secondary'       => null,
                'color_secondary_text'  => null,
                'color_section_bg'      => null,
                'color_section_bg_text' => null,
                'color_group_bg'        => null,
                'color_group_bg_text'   => null,
                'color_subgroup_bg'     => null,
                'color_subgroup_text'   => null,
            ],
            DocumentType::Bill->value => [
                'accent_color'          => null,
                'color_text'            => null,
                'color_secondary'       => null,
                'color_secondary_text'  => null,
                'color_section_bg'      => null,
                'color_section_bg_text' => null,
                'color_group_bg'        => null,
                'color_group_bg_text'   => null,
                'color_subgroup_bg'     => null,
                'color_subgroup_text'   => null,
            ],
            DocumentType::Estimate->value => [
                'accent_color'          => '#96693c',
                'color_text'            => '#293834',
                'color_secondary'       => '#f7f1eb',
                'color_secondary_text'  => '#96693c',
                'color_section_bg'      => '#e0b182',
                'color_section_bg_text' => '#293834',
                'color_group_bg'        => '#d4b896',
                'color_group_bg_text'   => '#293834',
                'color_subgroup_bg'     => '#f7f1eb',
                'color_subgroup_text'   => '#96693c',
            ],
            DocumentType::Contract->value => [
                'accent_color'          => '#4F46E5',
                'color_text'            => null,
                'color_secondary'       => null,
                'color_secondary_text'  => null,
                'color_section_bg'      => null,
                'color_section_bg_text' => null,
                'color_group_bg'        => null,
                'color_group_bg_text'   => null,
                'color_subgroup_bg'     => null,
                'color_subgroup_text'   => null,
            ],
            DocumentType::VariationOrder->value => [
                'accent_color'          => '#96693c',
                'color_text'            => '#293834',
                'color_secondary'       => '#f7f1eb',
                'color_secondary_text'  => '#96693c',
                'color_section_bg'      => '#e0b182',
                'color_section_bg_text' => '#293834',
                'color_group_bg'        => '#d4b896',
                'color_group_bg_text'   => '#293834',
                'color_subgroup_bg'     => '#f7f1eb',
                'color_subgroup_text'   => '#96693c',
            ],
        ];

        foreach ($colorsByType as $type => $colors) {
            DocumentDefault::query()
                ->where('type', $type)
                ->each(fn (DocumentDefault $default) => $default->updateQuietly($colors));
        }
    }
}

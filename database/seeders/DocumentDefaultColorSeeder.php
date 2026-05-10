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
            DocumentType::Estimate->value => [
                'accent_color'         => '#96693c',
                'color_text'           => '#293834',
                'color_secondary'      => '#f7f1eb',
                'color_secondary_text' => '#96693c',
                'color_section_bg'     => '#e0b182',
                'color_section_bg_text' => '#293834',
                'color_group_bg'       => '#d4b896',
                'color_group_bg_text'  => '#293834',
                'color_subgroup_bg'    => '#f7f1eb',
                'color_subgroup_text'  => '#96693c',
            ],
            DocumentType::VariationOrder->value => [
                'accent_color'         => '#96693c',
                'color_text'           => '#293834',
                'color_secondary'      => '#f7f1eb',
                'color_secondary_text' => '#96693c',
                'color_section_bg'     => '#e0b182',
                'color_section_bg_text' => '#293834',
                'color_group_bg'       => '#d4b896',
                'color_group_bg_text'  => '#293834',
                'color_subgroup_bg'    => '#f7f1eb',
                'color_subgroup_text'  => '#96693c',
            ],
        ];

        foreach ($colorsByType as $type => $colors) {
            DocumentDefault::query()
                ->where('type', $type)
                ->each(fn (DocumentDefault $default) => $default->updateQuietly($colors));
        }
    }
}

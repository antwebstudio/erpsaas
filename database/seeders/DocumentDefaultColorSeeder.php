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
                'accent_color' => '#C7B098',
                'color_text' => '#293834',
                'color_secondary' => '#d8d1c6',
                'color_secondary_text' => '#293834',
                'color_section_bg' => '#e62424ff',
                'color_section_bg_text' => '#293834',
                'color_group_bg' => '#e8e2db',
                'color_group_bg_text' => '#293834',
                'color_subgroup_bg' => '#ffffff',
                'color_subgroup_text' => '#C7B098',
            ],
            DocumentType::Bill->value => [
                'color_text' => '#293834',
                'color_secondary' => '#d8d1c6',
                'color_secondary_text' => '#293834',
                'color_section_bg' => '#e62424ff',
                'color_section_bg_text' => '#293834',
                'color_group_bg' => '#e8e2db',
                'color_group_bg_text' => '#293834',
                'color_subgroup_bg' => '#ffffff',
                'color_subgroup_text' => '#C7B098',
            ],
            DocumentType::Estimate->value => [
                'accent_color' => '#C7B098',
                'color_text' => '#293834',
                'color_secondary' => '#d8d1c6',
                'color_secondary_text' => '#293834',
                'color_section_bg' => '#e62424ff',
                'color_section_bg_text' => '#293834',
                'color_group_bg' => '#e8e2db',
                'color_group_bg_text' => '#293834',
                'color_subgroup_bg' => '#ffffff',
                'color_subgroup_text' => '#C7B098',
            ],
            DocumentType::RecurringInvoice->value => [
                'color_text' => '#293834',
                'color_secondary' => '#d8d1c6',
                'color_secondary_text' => '#293834',
                'color_section_bg' => '#e62424ff',
                'color_section_bg_text' => '#293834',
                'color_group_bg' => '#e8e2db',
                'color_group_bg_text' => '#293834',
                'color_subgroup_bg' => '#ffffff',
                'color_subgroup_text' => '#C7B098',
            ],
            DocumentType::Contract->value => [
                'color_text' => '#293834',
                'color_secondary' => '#d8d1c6',
                'color_secondary_text' => '#293834',
                'color_section_bg' => '#e62424ff',
                'color_section_bg_text' => '#293834',
                'color_group_bg' => '#e8e2db',
                'color_group_bg_text' => '#293834',
                'color_subgroup_bg' => '#ffffff',
                'color_subgroup_text' => '#C7B098',
            ],
            DocumentType::VariationOrder->value => [
                'accent_color' => '#C7B098',
                'color_text' => '#293834',
                'color_secondary' => '#d8d1c6',
                'color_secondary_text' => '#293834',
                'color_section_bg' => '#e62424ff',
                'color_section_bg_text' => '#293834',
                'color_group_bg' => '#e8e2db',
                'color_group_bg_text' => '#293834',
                'color_subgroup_bg' => '#ffffff',
                'color_subgroup_text' => '#C7B098',
            ],
        ];

        foreach ($colorsByType as $type => $colors) {
            DocumentDefault::query()
                ->where('type', $type)
                ->each(fn (DocumentDefault $default) => $default->updateQuietly($colors));
        }
    }
}

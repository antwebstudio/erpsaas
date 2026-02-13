<?php

namespace App\Filament\Company\Resources\Sales\EstimateTemplateResource\Pages;

use App\Filament\Company\Resources\Sales\EstimateTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEstimateTemplates extends ListRecords
{
    protected static string $resource = EstimateTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Company\Resources\Common\JobScopeDescriptionResource\Pages;

use App\Filament\Company\Resources\Common\JobScopeDescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobScopeDescriptions extends ListRecords
{
    protected static string $resource = JobScopeDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Company\Resources\Common\JobScopeResource\Pages;

use App\Filament\Company\Resources\Common\JobScopeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJobScopes extends ListRecords
{
    protected static string $resource = JobScopeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

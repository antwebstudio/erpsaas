<?php

namespace App\Filament\Company\Resources\Common\JobScopeOptionResource\Pages;

use App\Filament\Company\Resources\Common\JobScopeOptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJobScopeOption extends EditRecord
{
    protected static string $resource = JobScopeOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Company\Resources\Core\UserResource\Pages;

use App\Filament\Company\Resources\Core\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

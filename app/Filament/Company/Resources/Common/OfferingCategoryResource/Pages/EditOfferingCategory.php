<?php

namespace App\Filament\Company\Resources\Common\OfferingCategoryResource\Pages;

use App\Filament\Company\Resources\Common\OfferingCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfferingCategory extends EditRecord
{
    protected static string $resource = OfferingCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

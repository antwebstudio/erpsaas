<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\Pages;

use App\Filament\Actions\ExcelImportAction;
use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Imports\Common\ClientImporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->label('Import from Excel')
                ->importer(ClientImporter::class)
                ->visible(fn () => Auth::user()->can('create', static::getResource()::getModel())),
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return 'max-w-8xl';
    }
}

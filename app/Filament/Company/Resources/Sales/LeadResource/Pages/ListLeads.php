<?php

namespace App\Filament\Company\Resources\Sales\LeadResource\Pages;

use App\Filament\Actions\ExcelImportAction;
use App\Filament\Company\Resources\Sales\LeadResource;
use App\Filament\Imports\Common\LeadImporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->label('Import from Excel')
                ->importer(LeadImporter::class)
                ->visible(fn () => Auth::user()->can('create_sales::lead')),
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return 'max-w-8xl';
    }
}

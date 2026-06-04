<?php

namespace App\Filament\Company\Resources\Sales\ContractResource\Pages;

use App\Filament\Company\Resources\Sales\ContractResource;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}

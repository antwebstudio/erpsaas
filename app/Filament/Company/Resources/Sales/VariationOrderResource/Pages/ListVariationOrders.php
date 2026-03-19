<?php

namespace App\Filament\Company\Resources\Sales\VariationOrderResource\Pages;

use App\Filament\Company\Resources\Sales\VariationOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListVariationOrders extends ListRecords
{
    protected static string $resource = VariationOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return 'max-w-8xl';
    }
}

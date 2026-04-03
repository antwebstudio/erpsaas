<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use App\Filament\Company\Resources\Sales\VariationOrderResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VariationOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'variationOrders';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return VariationOrderResource::table($table)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(VariationOrderResource\Pages\CreateVariationOrder::getUrl(['client' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}

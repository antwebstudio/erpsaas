<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use App\Filament\Company\Resources\Sales\VariationOrderResource;
use App\Models\Accounting\VariationOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VariationOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'variationOrders';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $_ownerRecord, string $_pageClass): bool
    {
        return VariationOrderResource::canViewAny();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return VariationOrderResource::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                \App\Scopes\CurrentCompanyScope::class,
            ]))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(route('variation-orders.switch-and-create', ['client' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordUrl(fn (VariationOrder $record) => route('variation-orders.switch-and-view', $record));
    }
}

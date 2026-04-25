<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Models\Accounting\Estimate;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EstimatesRelationManager extends RelationManager
{
    protected static string $relationship = 'estimates';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return EstimateResource::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                \App\Scopes\CurrentCompanyScope::class,
            ]))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(route('estimates.switch-and-create', ['client' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordUrl(fn (Estimate $record) => route('estimates.switch-and-view', $record));
    }
}

<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use App\Filament\Company\Resources\Sales\ContractResource;
use App\Models\Accounting\Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ContractsRelationManager extends RelationManager
{
    protected static string $relationship = 'contracts';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $_ownerRecord, string $_pageClass): bool
    {
        return ContractResource::canViewAny();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return ContractResource::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                \App\Scopes\CurrentCompanyScope::class,
            ]))
            ->recordUrl(fn (Contract $record) => route('contracts.switch-and-view', $record));
    }
}

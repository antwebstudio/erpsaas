<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use App\Filament\Company\Resources\Sales\RecurringInvoiceResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecurringInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringInvoices';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $_ownerRecord, string $_pageClass): bool
    {
        return RecurringInvoiceResource::canViewAny();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return RecurringInvoiceResource::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                \App\Scopes\CurrentCompanyScope::class,
            ]))
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(RecurringInvoiceResource\Pages\CreateRecurringInvoice::getUrl(['client' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}

<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return InvoiceResource::table($table)
            ->modifyQueryUsing(function (Builder $query, Tables\Contracts\HasTable $livewire) {
                $query->withoutGlobalScopes([
                    \App\Scopes\CurrentCompanyScope::class,
                ]);

                if (property_exists($livewire, 'recurringInvoice')) {
                    $recurringInvoiceId = $livewire->recurringInvoice;

                    if (! empty($recurringInvoiceId)) {
                        $query->where('recurring_invoice_id', $recurringInvoiceId);
                    }
                }

                return $query;
            })
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->url(InvoiceResource\Pages\CreateInvoice::getUrl(['client' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordUrl(fn (Invoice $record) => route('invoices.switch-and-edit', $record));
    }
}

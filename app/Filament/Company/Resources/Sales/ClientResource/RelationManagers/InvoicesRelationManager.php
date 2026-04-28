<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;

use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $_ownerRecord, string $_pageClass): bool
    {
        return InvoiceResource::canViewAny();
    }

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
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\EditAction::make()
                            ->url(static fn (Invoice $record) => route('invoices.switch-and-edit', $record)),
                        Tables\Actions\ViewAction::make()
                            ->url(static fn (Invoice $record) => route('invoices.switch-and-view', $record)),
                        Invoice::getReplicateAction(Tables\Actions\ReplicateAction::class)
                            ->before(fn (Invoice $record) => auth()->user()->switchCompany($record->company))
                            ->successRedirectUrl(static fn (Invoice $replica) => route('invoices.switch-and-edit', $replica)),
                        Invoice::getApproveDraftAction(Tables\Actions\Action::class)
                            ->before(fn (Invoice $record) => auth()->user()->switchCompany($record->company)),
                        Invoice::getMarkAsSentAction(Tables\Actions\Action::class)
                            ->before(fn (Invoice $record) => auth()->user()->switchCompany($record->company)),
                        Invoice::getSendEmailAction(Tables\Actions\Action::class)
                            ->before(fn (Invoice $record) => auth()->user()->switchCompany($record->company)),
                        Tables\Actions\Action::make('recordPayment')
                            ->label('Record Payment')
                            ->icon('heroicon-m-credit-card')
                            ->visible(fn (Invoice $record) => $record->canRecordPayment())
                            ->url(fn (Invoice $record) => route('invoices.switch-and-record-payment', $record))
                            ->openUrlInNewTab(false),
                    ])->dropdown(false),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->recordUrl(fn (Invoice $record) => route('invoices.switch-and-edit', $record));
    }
}

<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\ContractResource\Pages\ViewContract;
use App\Models\Accounting\Contract;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use App\Models\Company;
use App\Models\Setting\CompanyProfile;
use App\Scopes\CurrentCompanyScope;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ContractResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = Contract::class;

    public static function shouldRegisterNavigation(): bool
    {
        if (config('erp.hide_contract_in_navigation', false)) {
            return false;
        }

        return static::canViewAny();
    }

    protected static ?string $slug = 'sales/contracts';

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'Contract';

    protected static ?string $pluralModelLabel = 'Contracts';

    protected static ?string $navigationGroup = 'Sales';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user->can('view_any_sales::contract') || $user->can('view_mine_sales::contract');
    }

    public static function getViewPaymentsAction(string $actionClass): MountableAction
    {
        return $actionClass::make('viewPayments')
            ->label('View Payments')
            ->icon('heroicon-o-banknotes')
            ->modalHeading('Payments')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->infolist(function (Infolist $infolist, Estimate $record): Infolist {
                $invoiceIds = Invoice::withoutGlobalScopes()
                    ->where('estimate_id', $record->id)
                    ->pluck('id');

                $dateFormat = \App\Services\CompanySettingsService::getDefaultDateFormat();

                $payments = Transaction::withoutGlobalScopes()
                    ->whereIn('transactionable_id', $invoiceIds)
                    ->where('transactionable_type', Invoice::class)
                    ->where('is_payment', true)
                    ->with(['transactionable' => fn ($q) => $q->withoutGlobalScopes()])
                    ->orderBy('posted_at')
                    ->get()
                    ->map(function (Transaction $t) use ($dateFormat): array {
                        $currency = $t->transactionable?->currency_code ?? CurrencyAccessor::getDefaultCurrency();

                        return [
                            'invoice_number' => $t->transactionable?->invoice_number ?? '-',
                            'posted_at' => $t->posted_at?->format($dateFormat) ?? '-',
                            'payment_method' => $t->payment_method?->getLabel() ?? '-',
                            'amount' => CurrencyConverter::formatCentsToMoney($t->amount, $currency),
                            'description' => $t->description ?? '-',
                            'reference' => $t->reference ?? '-',
                        ];
                    })
                    ->toArray();

                return $infolist
                    ->state(['payments' => $payments])
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label(\count($payments) === 0 ? 'No payments recorded yet.' : '')
                            ->schema([
                                TextEntry::make('invoice_number')->label('Invoice'),
                                TextEntry::make('posted_at')->label('Date'),
                                TextEntry::make('payment_method')->label('Method'),
                                TextEntry::make('amount')->label('Amount'),
                                TextEntry::make('description')->label('Description'),
                                TextEntry::make('reference')->label('Reference'),
                            ])
                            ->columns(6),
                    ]);
            });
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                CurrentCompanyScope::class,
            ])
            ->whereIn('status', [EstimateStatus::Accepted, EstimateStatus::Completed])
            ->isNotTemplate();

        $user = Auth::user();

        if ($user && ! $user->can('view_any_sales::contract') && $user->can('view_mine_sales::contract')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('clientAndLead', function (Builder $q) use ($user) {
                        $q->where('created_by', $user->id);
                    });
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    /**
     * Get payment type offerings from the company's configured payment category.
     *
     * @return \Illuminate\Support\Collection<int, Offering>
     */
    public static function getPaymentOfferings(?int $companyId = null): \Illuminate\Support\Collection
    {
        $company = $companyId ? Company::find($companyId) : auth()->user()?->currentCompany;

        if (! $company) {
            return collect();
        }

        $categoryId = $company->profile?->payment_offering_category_id;

        if (! $categoryId) {
            return collect();
        }

        $category = OfferingCategory::withoutGlobalScopes()->find($categoryId);

        if (! $category) {
            return collect();
        }

        return $category->offerings()
            ->withoutGlobalScopes([
                CurrentCompanyScope::class,
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get payment types as key => label array.
     * Uses offerings from the "Payment" category, with a hardcoded fallback.
     */
    public static function getPaymentTypes(): array
    {
        $offerings = static::getPaymentOfferings();

        if ($offerings->isNotEmpty()) {
            return $offerings->mapWithKeys(fn (Offering $o) => [
                'invoice_offering_' . $o->id => $o->name,
            ])->all();
        }

        // Fallback when offerings have not been seeded yet
        return [
            'invoiceDeposit' => 'Deposit payment',
            'invoiceWorkCommencement' => 'Work commencement payment',
            'invoiceProgressive' => 'Progressive payment',
            'invoiceVariationOrder' => 'Variation order payment',
            'invoiceWiringWork' => 'Wiring work payment',
            'invoiceFinal' => 'Final payment',
        ];
    }

    public static function createPaymentInvoice(Estimate $record, string $description, ?int $offeringId = null): Invoice
    {
        $company = $record->templateCompany ?? $record->company;

        $defaultInvoice = $company->defaultInvoice;

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $record->client_id,
            'estimate_id' => $record->id,
            'currency_code' => $record->currency_code,
            'invoice_number' => Invoice::getNextDocumentNumber($company),
            'date' => company_today(),
            'due_date' => company_today(),
            'status' => InvoiceStatus::Draft,
            'discount_method' => DocumentDiscountMethod::PerLineItem,
            'header' => $defaultInvoice->header ?? '',
            'subheader' => $defaultInvoice->subheader ?? '',
            'subtotal' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 0,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $group = DocumentLineItemGroup::create([
            'company_id' => $company->id,
            'documentable_type' => $invoice->getMorphClass(),
            'documentable_id' => $invoice->id,
            'name' => '',
            'order' => 1,
        ]);

        $lineItemData = [
            'company_id' => $company->id,
            'group_id' => $group->id,
            'description' => $offeringId ? null : $description,
            'quantity' => 1,
            'unit_price' => 0,
            'line_number' => 1,
        ];

        if ($offeringId) {
            $lineItemData['offering_id'] = $offeringId;
        }

        $invoice->lineItems()->create($lineItemData);

        return $invoice;
    }

    /**
     * Build the "Generate Invoice" action list for payment types.
     * Extracts offering_id from seeded offerings when available.
     *
     * @param  class-string  $actionClass  The Filament Action class to use (Tables\Actions\Action or Actions\Action)
     */
    public static function buildPaymentInvoiceActions(string $actionClass, ?int $companyId = null): array
    {
        if ($companyId) {
            $offerings = static::getPaymentOfferings($companyId);

            if ($offerings->isNotEmpty()) {
                return $offerings->map(
                    fn (Offering $offering) => $actionClass::make('invoice_offering_' . $offering->id)
                        ->label($offering->name)
                        ->icon('heroicon-o-document-plus')
                        ->visible(fn (Estimate $record) => ! $record->isCompleted() && auth()->user()->canForCompany($record->template_company_id ?? $record->company_id, 'create_sales::invoice'))
                        ->action(function (Estimate $record) use ($offering) {
                            $invoice = static::createPaymentInvoice($record, $offering->name, $offering->id);
                            redirect(route('invoices.switch-and-edit', $invoice));
                        })
                )->values()->all();
            }

            return [];
        }

        $paymentCategoryIds = CompanyProfile::withoutGlobalScopes()->pluck('payment_offering_category_id')->filter()->unique()->toArray();

        $offerings = Offering::withoutGlobalScopes()
            ->whereHas('categories', fn ($q) => $q->withoutGlobalScopes()->whereIn('offering_categories.id', $paymentCategoryIds))
            ->with(['categories' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();

        return $offerings->map(function (Offering $offering) use ($actionClass) {
            return $actionClass::make('invoice_offering_' . $offering->id)
                ->label($offering->name)
                ->icon('heroicon-o-document-plus')
                ->visible(function (Estimate $record) use ($offering) {
                    if ($record->isCompleted()) {
                        return false;
                    }

                    $effectiveCompanyId = $record->template_company_id ?? $record->company_id;
                    $categoryId = $record->templateCompany?->profile?->payment_offering_category_id
                        ?? $record->company->profile?->payment_offering_category_id;

                    return $categoryId &&
                        $offering->categories->contains('id', $categoryId) &&
                        auth()->user()->canForCompany($effectiveCompanyId, 'create_sales::invoice');
                })
                ->action(function (Estimate $record) use ($offering) {
                    $invoice = static::createPaymentInvoice($record, $offering->name, $offering->id);
                    redirect(route('invoices.switch-and-edit', $invoice));
                });
        })->values()->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Issuing Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('reference_number')
                    ->label('Reference Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('estimate_number')
                    ->label('Estimate Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->hidden()
                    ->sortable()
                    ->searchable()
                    ->url(static function (Contract $record) {
                        if (! $record->client_id) {
                            return null;
                        }

                        $client = $record->clientAndLead;

                        if ($client && $client->type === 'client') {
                            return AllClientResource::getUrl('view', ['record' => $record->client_id]);
                        }

                        return LeadResource::getUrl('view', ['record' => $record->client_id]);
                    }),
                TextColumn::make('total')
                    ->currencyWithConversion(static fn (Estimate $record) => $record->currency_code)
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\IconColumn::make('archived_at')
                    ->label('Archived')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('')
                    ->colors([
                        'warning' => fn ($state) => $state !== null,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(static fn (Contract $record) => ViewContract::getUrl(['record' => $record]))
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('contract_state')
                    ->label('State')
                    ->form([
                        Forms\Components\Select::make('state')
                            ->label('State')
                            ->options([
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'archived' => 'Archived',
                                'all' => 'All Contracts',
                            ])
                            ->default('active'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['state'] ?? 'active') {
                            'completed' => $query->completed()->notArchived(),
                            'archived' => $query->archived(),
                            'all' => $query,
                            default => $query->notCompleted()->notArchived(),
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['state'] ?? 'active') {
                            'completed' => 'State: Completed',
                            'archived' => 'State: Archived',
                            'all' => 'State: All Contracts',
                            default => null,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->url(static fn (Contract $record) => ViewContract::getUrl(['record' => $record])),
                    static::getViewPaymentsAction(Tables\Actions\Action::class),
                    Estimate::getDownloadMergedPdfAction(Tables\Actions\Action::class),
                    static::getModel()::getPreviewAction(Tables\Actions\Action::class),
                    Tables\Actions\Action::make('complete')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->visible(fn (Contract $record) => ! $record->isCompleted() && auth()->user()->canForCompany($record->company_id, 'complete_sales::contract'))
                        ->requiresConfirmation()
                        ->action(function (Contract $record) {
                            $record->complete();
                            Notification::make()
                                ->title('Contract marked as completed')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('uncomplete')
                        ->label('Mark as Active')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Contract $record) => $record->isCompleted() && auth()->user()->canForCompany($record->company_id, 'complete_sales::contract'))
                        ->requiresConfirmation()
                        ->action(function (Contract $record) {
                            $record->uncomplete();
                            Notification::make()
                                ->title('Contract marked as active')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Contract $record) => ! $record->isArchived() && ! $record->isCompleted() && auth()->user()->can('update', $record))
                        ->action(function (Contract $record) {
                            $record->archive();
                            Notification::make()
                                ->title('Contract archived')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('unarchive')
                        ->label('Unarchive')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->visible(fn (Contract $record) => $record->isArchived() && auth()->user()->can('update', $record))
                        ->requiresConfirmation()
                        ->action(function (Contract $record) {
                            $record->unarchive();
                            Notification::make()
                                ->title('Contract unarchived')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('More')
                    ->button()
                    ->outlined()
                    ->dropdownPlacement('bottom-end')
                    ->icon('heroicon-m-chevron-down')
                    ->iconPosition(IconPosition::After),

                Tables\Actions\ActionGroup::make(
                    static::buildPaymentInvoiceActions(Tables\Actions\Action::class)
                )
                    ->label('Generate Invoice')
                    ->button()
                    ->outlined()
                    ->dropdownPlacement('bottom-end')
                    ->icon('heroicon-m-chevron-down')
                    ->iconPosition(IconPosition::After),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('complete')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->visible(fn () => auth()->user()->can('complete_sales::contract'))
                        ->requiresConfirmation()
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->complete())
                        ->after(fn () => Notification::make()->title('Contracts marked as completed')->success()->send()),
                    Tables\Actions\BulkAction::make('uncomplete')
                        ->label('Mark as Active')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn () => auth()->user()->can('complete_sales::contract'))
                        ->requiresConfirmation()
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->uncomplete())
                        ->after(fn () => Notification::make()->title('Contracts marked as active')->success()->send()),
                    Tables\Actions\BulkAction::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->archive())
                        ->after(fn () => Notification::make()->title('Contracts archived')->success()->send()),
                    Tables\Actions\BulkAction::make('unarchive')
                        ->label('Unarchive')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->unarchive())
                        ->after(fn () => Notification::make()->title('Contracts unarchived')->success()->send()),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Company\Resources\Sales\ContractResource\Pages\ListContracts::route('/'),
            'view' => \App\Filament\Company\Resources\Sales\ContractResource\Pages\ViewContract::route('/{record}'),
        ];
    }
}

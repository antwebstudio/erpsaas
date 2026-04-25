<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Accounting\InvoiceStatus;
use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use App\Filament\Company\Resources\Sales\ContractResource\Pages\ViewContract;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages\ViewEstimate;
use App\Models\Accounting\Contract;
use App\Models\Accounting\DocumentLineItemGroup;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Scopes\CurrentCompanyScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use App\Filament\Company\Resources\Sales\AllClientResource;
use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\LeadResource;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                CurrentCompanyScope::class,
            ])
            ->where('status', EstimateStatus::Accepted)
            ->isNotTemplate();
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
    public static function getPaymentOfferings(): \Illuminate\Support\Collection
    {
        $company = auth()->user()?->currentCompany;

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
            'invoiceDeposit'          => 'Deposit payment',
            'invoiceWorkCommencement' => 'Work commencement payment',
            'invoiceProgressive'      => 'Progressive payment',
            'invoiceVariationOrder'   => 'Variation order payment',
            'invoiceWiringWork'       => 'Wiring work payment',
            'invoiceFinal'            => 'Final payment',
        ];
    }

    public static function createPaymentInvoice(Estimate $record, string $description, ?int $offeringId = null): Invoice
    {
        $company = $record->company;

        $defaultInvoice = $company->defaultInvoice;

        $invoice = Invoice::create([
            'company_id'      => $company->id,
            'client_id'       => $record->client_id,
            'estimate_id'     => $record->id,
            'currency_code'   => $record->currency_code,
            'invoice_number'  => Invoice::getNextDocumentNumber($company),
            'date'            => company_today(),
            'due_date'        => company_today(),
            'status'          => InvoiceStatus::Draft,
            'discount_method' => DocumentDiscountMethod::PerLineItem,
            'header'          => $defaultInvoice->header ?? '',
            'subheader'       => $defaultInvoice->subheader ?? '',
            'subtotal'        => 0,
            'tax_total'       => 0,
            'discount_total'  => 0,
            'total'           => 0,
            'created_by'      => auth()->id(),
            'updated_by'      => auth()->id(),
        ]);

        $group = DocumentLineItemGroup::create([
            'company_id'        => $company->id,
            'documentable_type' => $invoice->getMorphClass(),
            'documentable_id'   => $invoice->id,
            'name'              => '',
            'order'             => 1,
        ]);

        $lineItemData = [
            'company_id'  => $company->id,
            'group_id'    => $group->id,
            'description' => $offeringId ? null : $description,
            'quantity'    => 1,
            'unit_price'  => 0,
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
    public static function buildPaymentInvoiceActions(string $actionClass): array
    {
        $offerings = static::getPaymentOfferings();

        if ($offerings->isNotEmpty()) {
            return $offerings->map(
                fn (Offering $offering) => $actionClass::make('invoice_offering_' . $offering->id)
                    ->label($offering->name)
                    ->icon('heroicon-o-document-plus')
                    ->action(function (Estimate $record) use ($offering) {
                        $invoice = static::createPaymentInvoice($record, $offering->name, $offering->id);
                        redirect(route('invoices.switch-and-edit', $invoice));
                    })
            )->values()->all();
        }

        // Fallback to hardcoded payment types
        return collect(static::getPaymentTypes())->map(
            fn (string $label, string $name) => $actionClass::make($name)
                ->label($label)
                ->icon('heroicon-o-document-plus')
                ->action(function (Estimate $record) use ($label) {
                    $invoice = static::createPaymentInvoice($record, $label);
                    redirect(route('invoices.switch-and-edit', $invoice));
                })
        )->values()->all();
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(static fn (Contract $record) => ViewContract::getUrl(['record' => $record])),
                Estimate::getDownloadMergedPdfAction(Tables\Actions\Action::class),
                static::getModel()::getPreviewAction(Tables\Actions\Action::class),
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

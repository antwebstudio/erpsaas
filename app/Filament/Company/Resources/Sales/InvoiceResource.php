<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentStatus;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Setting\PaymentTerms;
use App\Filament\Company\Resources\Sales\ClientResource\RelationManagers\InvoicesRelationManager;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages;
use App\Filament\Company\Resources\Sales\InvoiceResource\Widgets;
use App\Filament\Exports\Accounting\InvoiceExporter;
use App\Filament\Forms\Components\CreateAdjustmentSelect;
use App\Filament\Forms\Components\CreateClientSelect;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\CreateOfferingSelect;
use App\Filament\Forms\Components\CustomTableRepeater;
use App\Filament\Forms\Components\DocumentFooterSection;
use App\Filament\Forms\Components\DocumentHeaderSection;
use App\Filament\Forms\Components\DocumentTotals;
use App\Filament\Tables\Actions\ReplicateBulkAction;
use App\Filament\Tables\Columns;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Guava\FilamentClusters\Forms\Cluster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class InvoiceResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = Invoice::class;

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        $settings = $company->defaultInvoice;

        return $form
            ->schema([
                DocumentHeaderSection::make('Invoice Header')
                    ->defaultHeader($settings->header)
                    ->defaultSubheader($settings->subheader),
                Forms\Components\Section::make('Invoice Details')
                    ->schema([
                        Forms\Components\Split::make([
                            Forms\Components\Group::make([
                                CreateClientSelect::make('client_id')
                                    ->label('Client')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $currencyCode = Client::find($state)?->currency_code;

                                        if ($currencyCode) {
                                            $set('currency_code', $currencyCode);
                                        }
                                    }),
                                CreateCurrencySelect::make('currency_code')
                                    ->disabled(function (?Invoice $record) {
                                        return $record?->hasPayments();
                                    }),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('Invoice number')
                                    ->default(static fn () => Invoice::getNextDocumentNumber()),
                                Forms\Components\TextInput::make('order_number')
                                    ->label('P.O/S.O Number'),
                                Cluster::make([
                                    Forms\Components\DatePicker::make('date')
                                        ->label('Invoice date')
                                        ->live()
                                        ->default(company_today()->toDateString())
                                        ->disabled(function (?Invoice $record) {
                                            return $record?->hasPayments();
                                        })
                                        ->columnSpan(2)
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                            $date = Carbon::parse($state)->toDateString();
                                            $dueDate = Carbon::parse($get('due_date'))->toDateString();

                                            if ($date && $dueDate && $date > $dueDate) {
                                                $set('due_date', $date);
                                            }

                                            // Update due date based on payment terms if selected
                                            $paymentTerms = $get('payment_terms');
                                            if ($date && $paymentTerms && $paymentTerms !== 'custom') {
                                                $terms = PaymentTerms::parse($paymentTerms);
                                                $set('due_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
                                            }
                                        }),
                                    Forms\Components\Select::make('payment_terms')
                                        ->label('Payment terms')
                                        ->options(function () {
                                            return collect(PaymentTerms::cases())
                                                ->mapWithKeys(function (PaymentTerms $paymentTerm) {
                                                    return [$paymentTerm->value => $paymentTerm->getLabel()];
                                                })
                                                ->put('custom', 'Custom')
                                                ->toArray();
                                        })
                                        ->selectablePlaceholder(false)
                                        ->default($settings->payment_terms->value)
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                            if (! $state || $state === 'custom') {
                                                return;
                                            }

                                            $date = $get('date');
                                            if ($date) {
                                                $terms = PaymentTerms::parse($state);
                                                $set('due_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
                                            }
                                        }),
                                ])
                                    ->label('Invoice date')
                                    ->columns(3),
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Payment due')
                                    ->default(function () use ($settings) {
                                        return company_today()->addDays($settings->payment_terms->getDays())->toDateString();
                                    })
                                    ->minDate(static function (Forms\Get $get) {
                                        return Carbon::parse($get('date'))->toDateString() ?? company_today()->toDateString();
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $invoiceDate = $get('date');
                                        $paymentTerms = $get('payment_terms');

                                        if (! $invoiceDate || $paymentTerms === 'custom' || ! $paymentTerms) {
                                            return;
                                        }

                                        $term = PaymentTerms::parse($paymentTerms);
                                        $expectedDueDate = Carbon::parse($invoiceDate)->addDays($term->getDays());

                                        if (! Carbon::parse($state)->isSameDay($expectedDueDate)) {
                                            $set('payment_terms', 'custom');
                                        }
                                    }),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount method')
                                    ->options(DocumentDiscountMethod::class)
                                    ->softRequired()
                                    ->default($settings->discount_method)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $discountMethod = DocumentDiscountMethod::parse($state);

                                        if ($discountMethod->isPerDocument()) {
                                            $set('lineItems.*.salesDiscounts', []);
                                        }
                                    })
                                    ->live(),
                                CreateAdjustmentSelect::make('salesTaxes')
                                    ->label('Document Taxes')
                                    ->category(AdjustmentCategory::Tax)
                                    ->type(AdjustmentType::Sales)
                                    ->adjustmentsRelationship('salesTaxes')
                                    ->forceEnableRelationship()
                                    ->preload()
                                    ->multiple()
                                    ->live()
                                    ->searchable()
                                    ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false)),
                            ])->grow(true),
                        ])->from('md'),
                        Forms\Components\Repeater::make('lineItemGroups')
                            ->relationship('lineItemGroups')
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(true)
                            ->orderColumn('order')
                            ->defaultItems(1)
                            ->label('Item Groups')
                            ->hiddenLabel()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Section Name (Optional)')
                                    ->placeholder('e.g. Materials, Labor')
                                    ->columnSpanFull(),
                                CustomTableRepeater::make('items')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->saveRelationshipsUsing(null)
                                    ->dehydrated(true)
                                    ->reorderable()
                                    ->orderColumn('line_number')
                                    ->reorderAtStart()
                                    ->cloneable()
                                    ->addActionLabel('Add an item')
                                    ->headers(function (Forms\Get $get) use ($settings) {
                                        $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                        $hasDiscounts = $discountMethod->isPerLineItem();

                                        $headers = [
                                            Header::make($settings->resolveColumnLabel('item_name', 'Items'))
                                                ->width('30%'),
                                            Header::make('Unit')
                                                ->width('10%')
                                                ->markAsRequired(false),
                                            Header::make($settings->resolveColumnLabel('unit_name', 'Quantity'))
                                                ->width('10%'),
                                            Header::make($settings->resolveColumnLabel('price_name', 'Price'))
                                                ->width('10%'),
                                        ];

                                        if ($hasDiscounts) {
                                            $headers[] = Header::make('Adjustments')->width('30%');
                                        } else {
                                            $headers[] = Header::make('Taxes')->width('30%');
                                        }

                                        $headers[] = Header::make($settings->resolveColumnLabel('amount_name', 'Amount'))
                                            ->width('10%')
                                            ->align('right');

                                        return $headers;
                                    })
                                    ->schema([
                                        Forms\Components\Hidden::make('id'),
                                        Forms\Components\Group::make([
                                            CreateOfferingSelect::make('offering_id')
                                                ->label('Item')
                                                ->hiddenLabel()
                                                ->placeholder('Select item')
                                                ->live()
                                                ->inlineSuffix()
                                                ->sellable()
                                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state, ?DocumentLineItem $record) {
                                                    $offeringId = $state;
                                                    $discountMethod = DocumentDiscountMethod::parse($get('../../../../discount_method'));
                                                    $isPerLineItem = $discountMethod->isPerLineItem();

                                                    $existingTaxIds = [];
                                                    $existingDiscountIds = [];

                                                    if ($record) {
                                                        $existingTaxIds = $record->salesTaxes()->pluck('adjustments.id')->toArray();
                                                        if ($isPerLineItem) {
                                                            $existingDiscountIds = $record->salesDiscounts()->pluck('adjustments.id')->toArray();
                                                        }
                                                    }

                                                    $with = [
                                                        'salesTaxes' => static function ($query) use ($existingTaxIds) {
                                                            $query->where(static function ($query) use ($existingTaxIds) {
                                                                $query->where('status', AdjustmentStatus::Active)
                                                                    ->orWhereIn('adjustments.id', $existingTaxIds);
                                                            });
                                                        },
                                                    ];

                                                    if ($isPerLineItem) {
                                                        $with['salesDiscounts'] = static function ($query) use ($existingDiscountIds) {
                                                            $query->where(static function ($query) use ($existingDiscountIds) {
                                                                $query->where('status', AdjustmentStatus::Active)
                                                                    ->orWhereIn('adjustments.id', $existingDiscountIds);
                                                            });
                                                        };
                                                    }

                                                    $offeringRecord = Offering::with($with)->find($offeringId);

                                                    if (! $offeringRecord) {
                                                        return;
                                                    }

                                                    $unitPrice = CurrencyConverter::convertCentsToFormatSimple($offeringRecord->price, 'USD');

                                                    $set('description', $offeringRecord->description);
                                                    $set('unit', $offeringRecord->unit);
                                                    $set('unit_price', $unitPrice);
                                                    $set('salesTaxes', $offeringRecord->salesTaxes->pluck('id')->toArray());

                                                    if ($isPerLineItem) {
                                                        $set('salesDiscounts', $offeringRecord->salesDiscounts->pluck('id')->toArray());
                                                    }
                                                }),
                                            Forms\Components\TextInput::make('description')
                                                ->placeholder('Enter item description')
                                                ->hiddenLabel(),
                                        ])->columnSpan(1),
                                        Forms\Components\TextInput::make('unit')
                                            ->placeholder('Unit')
                                            ->hiddenLabel(),
                                        Forms\Components\TextInput::make('quantity')
                                            ->required()
                                            ->numeric()
                                            ->live(onBlur: true)
                                            ->maxValue(9999999999.99)
                                            ->default(1),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->hiddenLabel()
                                            ->money(useAffix: false)
                                            ->live(onBlur: true)
                                            ->required()
                                            ->default(0),
                                        Forms\Components\Group::make([
                                            CreateAdjustmentSelect::make('salesTaxes', true)
                                                ->label('Taxes')
                                                ->hiddenLabel()
                                                ->placeholder('Select taxes')
                                                ->category(AdjustmentCategory::Tax)
                                                ->type(AdjustmentType::Sales)
                                                ->adjustmentsRelationship('salesTaxes')
                                                ->saveRelationshipsUsing(null)
                                                ->dehydrated(true)
                                                ->inlineSuffix()
                                                ->preload()
                                                ->multiple()
                                                ->live()
                                                ->searchable(),
                                            CreateAdjustmentSelect::make('salesDiscounts', true)
                                                ->label('Discounts')
                                                ->hiddenLabel()
                                                ->placeholder('Select discounts')
                                                ->category(AdjustmentCategory::Discount)
                                                ->type(AdjustmentType::Sales)
                                                ->adjustmentsRelationship('salesDiscounts')
                                                ->saveRelationshipsUsing(null)
                                                ->dehydrated(true)
                                                ->inlineSuffix()
                                                ->multiple()
                                                ->live()
                                                ->hidden(function (Forms\Get $get) {
                                                    $discountMethod = DocumentDiscountMethod::parse($get('../../../../discount_method'));

                                                    return $discountMethod->isPerDocument();
                                                })
                                                ->searchable(),
                                        ])->columnSpan(1),
                                        Forms\Components\Placeholder::make('total')
                                            ->hiddenLabel()
                                            ->extraAttributes(['class' => 'text-left sm:text-right'])
                                            ->content(function (Forms\Get $get) {
                                                $quantity = max((float) ($get('quantity') ?? 0), 0);
                                                $unitPrice = CurrencyConverter::isValidAmount($get('unit_price'), 'USD')
                                                    ? CurrencyConverter::convertToFloat($get('unit_price'), 'USD')
                                                    : 0;
                                                $salesTaxes = $get('salesTaxes') ?? [];
                                                $salesDiscounts = $get('salesDiscounts') ?? [];
                                                $currencyCode = $get('../../../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                                                $subtotal = $quantity * $unitPrice;

                                                $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                                                static $companyAdjustments = [];
                                                $companyId = auth()->user()->current_company_id;

                                                if (! isset($companyAdjustments[$companyId])) {
                                                    $companyAdjustments[$companyId] = Adjustment::where('company_id', $companyId)->get()->keyBy('id');
                                                }

                                                 $taxAmountInCents = 0;
                                                 foreach ($salesTaxes as $id) {
                                                     $adjustment = $companyAdjustments[$companyId]->get($id);
                                                     if ($adjustment) {
                                                         $taxAmountInCents += $adjustment->computation->isPercentage()
                                                             ? RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'))
                                                             : $adjustment->getRawOriginal('rate');
                                                     }
                                                 }

                                                 $discountAmountInCents = 0;
                                                 foreach ($salesDiscounts as $id) {
                                                     $adjustment = $companyAdjustments[$companyId]->get($id);
                                                     if ($adjustment) {
                                                         $discountAmountInCents += $adjustment->computation->isPercentage()
                                                             ? RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'))
                                                             : $adjustment->getRawOriginal('rate');
                                                     }
                                                 }

                                                // Final total
                                                $totalInCents = $subtotalInCents + ($taxAmountInCents - $discountAmountInCents);

                                                return CurrencyConverter::formatCentsToMoney($totalInCents, $currencyCode);
                                            }),
                                    ]),
                            ]),
                        DocumentTotals::make()
                            ->type(DocumentType::Invoice),
                        Forms\Components\Textarea::make('terms')
                            ->default($settings->terms)
                            ->columnSpanFull(),
                    ]),
                DocumentFooterSection::make('Invoice Footer')
                    ->defaultFooter($settings->footer),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->modifyQueryUsing(function (Builder $query, Tables\Contracts\HasTable $livewire) {
                if (property_exists($livewire, 'recurringInvoice')) {
                    $recurringInvoiceId = $livewire->recurringInvoice;

                    if (! empty($recurringInvoiceId)) {
                        $query->where('recurring_invoice_id', $recurringInvoiceId);
                    }
                }

                return $query;
            })
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due')
                    ->asRelativeDay()
                    ->sortable()
                    ->hideOnTabs(['draft']),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Number')
                    ->searchable()
                    ->description(function (Invoice $record) {
                        return $record->source_type?->getLabel();
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable()
                    ->hiddenOn(InvoicesRelationManager::class),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn (Invoice $record) => $record->currency_code)
                    ->sortable()
                    ->toggleable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount paid')
                    ->currencyWithConversion(static fn (Invoice $record) => $record->currency_code)
                    ->sortable()
                    ->alignEnd()
                    ->showOnTabs(['unpaid']),
                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Amount due')
                    ->currencyWithConversion(static fn (Invoice $record) => $record->currency_code)
                    ->sortable()
                    ->alignEnd()
                    ->hideOnTabs(['draft']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->hiddenOn(InvoicesRelationManager::class),
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class)
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('has_payments')
                    ->label('Has payments')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('payments'),
                        false: fn (Builder $query) => $query->whereDoesntHave('payments'),
                    ),
                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Source type')
                    ->options([
                        DocumentType::Estimate->value => DocumentType::Estimate->getLabel(),
                        DocumentType::RecurringInvoice->value => DocumentType::RecurringInvoice->getLabel(),
                    ])
                    ->native(false)
                    ->query(function (Builder $query, array $data) {
                        $sourceType = $data['value'] ?? null;

                        return match ($sourceType) {
                            DocumentType::Estimate->value => $query->whereNotNull('estimate_id'),
                            DocumentType::RecurringInvoice->value => $query->whereNotNull('recurring_invoice_id'),
                            default => $query,
                        };
                    }),
                DateRangeFilter::make('date')
                    ->fromLabel('From date')
                    ->untilLabel('To date')
                    ->indicatorLabel('Date'),
                DateRangeFilter::make('due_date')
                    ->fromLabel('From due date')
                    ->untilLabel('To due date')
                    ->indicatorLabel('Due'),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(InvoiceExporter::class),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\EditAction::make()
                            ->url(static fn (Invoice $record) => Pages\EditInvoice::getUrl(['record' => $record])),
                        Tables\Actions\ViewAction::make()
                            ->url(static fn (Invoice $record) => Pages\ViewInvoice::getUrl(['record' => $record])),
                        Invoice::getReplicateAction(Tables\Actions\ReplicateAction::class),
                        Invoice::getApproveDraftAction(Tables\Actions\Action::class),
                        Invoice::getMarkAsSentAction(Tables\Actions\Action::class),
                        Tables\Actions\Action::make('recordPayment')
                            ->label('Record Payment')
                            ->icon('heroicon-m-credit-card')
                            ->visible(function (Invoice $record) {
                                return $record->canRecordPayment();
                            })
                            ->url(fn (Invoice $record) => Pages\RecordPayments::getUrl([
                                'tableFilters' => [
                                    'client_id' => ['value' => $record->client_id],
                                    'currency_code' => ['value' => $record->currency_code],
                                ],
                                'invoiceId' => $record->id,
                            ]))
                            ->openUrlInNewTab(false),
                    ])->dropdown(false),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ReplicateBulkAction::make()
                        ->label('Replicate')
                        ->modalWidth(MaxWidth::Large)
                        ->modalDescription('Replicating invoices will also replicate their line items. Are you sure you want to proceed?')
                        ->successNotificationTitle('Invoices replicated successfully')
                        ->failureNotificationTitle('Failed to replicate invoices')
                        ->databaseTransaction()
                        ->excludeAttributes([
                            'status',
                            'amount_paid',
                            'amount_due',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                            'invoice_number',
                            'date',
                            'due_date',
                            'approved_at',
                            'paid_at',
                            'last_sent_at',
                            'last_viewed_at',
                        ])
                        ->beforeReplicaSaved(function (Invoice $replica) {
                            $replica->status = InvoiceStatus::Draft;
                            $replica->invoice_number = Invoice::getNextDocumentNumber();
                            $replica->date = company_today();
                            $replica->due_date = company_today()->addDays($replica->company->defaultInvoice->payment_terms->getDays());
                        })
                        ->afterReplicaSaved(function (Invoice $original, Invoice $replica) {
                            $original->replicateLineItems($replica);
                        }),

                    Tables\Actions\BulkAction::make('approveDrafts')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->databaseTransaction()
                        ->successNotificationTitle('Invoices approved')
                        ->failureNotificationTitle('Failed to Approve Invoices')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Invoice $record) => ! $record->canBeApproved());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Approval failed')
                                    ->body('Only draft invoices can be approved. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Invoice $record) {
                                $record->approveDraft();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsSent')
                        ->label('Mark as sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->databaseTransaction()
                        ->successNotificationTitle('Invoices sent')
                        ->failureNotificationTitle('Failed to Mark Invoices as Sent')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Invoice $record) => ! $record->canBeMarkedAsSent());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Sending failed')
                                    ->body('Only unsent invoices can be marked as sent. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Invoice $record) {
                                $record->markAsSent();
                            });

                            $action->success();
                        }),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'lineItems.salesTaxes',
                'lineItems.salesDiscounts',
                'salesTaxes',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'record-payments' => Pages\RecordPayments::route('/record-payments'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\InvoiceOverview::class,
        ];
    }
}

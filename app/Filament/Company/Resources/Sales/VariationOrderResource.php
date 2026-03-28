<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentStatus;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\VariationOrderStatus;
use App\Enums\Setting\PaymentTerms;
use App\Filament\Company\Resources\Sales\VariationOrderResource\Pages;
use App\Filament\Forms\Components\CreateAdjustmentSelect;
use App\Filament\Forms\Components\CreateClientSelect;
use App\Filament\Forms\Components\CreateCurrencySelect;
use App\Filament\Forms\Components\CreateOfferingSelect;
use App\Filament\Forms\Components\CustomTableRepeater;
use App\Filament\Forms\Components\DocumentFooterSection;
use App\Filament\Forms\Components\DocumentHeaderSection;
use App\Filament\Forms\Components\DocumentTotals;
use Guava\FilamentClusters\Forms\Cluster;
use Illuminate\Support\Carbon;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Common\Offering;
use App\Models\Accounting\VariationOrder;
use App\Models\Company;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use App\Filament\Tables\Columns;
use App\Filament\Tables\Filters\DateRangeFilter;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Scopes\CurrentCompanyScope;
use Illuminate\Database\Eloquent\Builder;

class VariationOrderResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = VariationOrder::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                CurrentCompanyScope::class,
            ]);
    }

    protected static ?string $modelLabel = 'Variation Order';

    protected static ?string $pluralModelLabel = 'Variation Orders';

    protected static ?string $slug = 'variation-orders';

    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        /** @var Company $company */
        $company = \Illuminate\Support\Facades\Auth::user()->currentCompany;

        $settings = $company->defaultVariationOrder;

        return $form
            ->schema([
                DocumentHeaderSection::make('Variation Order Header')
                    ->defaultHeader($settings?->header)
                    ->defaultSubheader($settings?->subheader),
                Forms\Components\Section::make('Variation Order Details')
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

                                        $currencyCode = \App\Models\Common\Client::find($state)?->currency_code;

                                        if ($currencyCode) {
                                            $set('currency_code', $currencyCode);
                                        }
                                    }),
                                Forms\Components\Select::make('estimate_id')
                                    ->label('Link to Estimate')
                                    ->relationship('estimate', 'estimate_number')
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                CreateCurrencySelect::make('currency_code'),

                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('vo_number')
                                    ->label('VO number')
                                    ->default(static fn () => VariationOrder::getNextDocumentNumber())
                                    ->required(),
                                Forms\Components\TextInput::make('reference_number')
                                    ->label('Reference number'),
                                Cluster::make([
                                    Forms\Components\DatePicker::make('date')
                                        ->label('Variation Order date')
                                        ->live()
                                        ->default(company_today()->toDateString())
                                        ->columnSpan(2)
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                            $date = Carbon::parse($state)->toDateString();
                                            $expiryDate = Carbon::parse($get('expiry_date'))->toDateString();

                                            if ($date && $expiryDate && $date > $expiryDate) {
                                                $set('expiry_date', $date);
                                            }

                                            $paymentTerms = $get('payment_terms');
                                            if ($date && $paymentTerms && $paymentTerms !== 'custom') {
                                                $terms = PaymentTerms::parse($paymentTerms);
                                                $set('expiry_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
                                            }
                                        }),
                                    Forms\Components\Select::make('payment_terms')
                                        ->hidden(fn () => ! config('erp.show_expiry_date', true))
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
                                        ->default($settings?->payment_terms?->value ?? PaymentTerms::Net30->value)
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                            if (! $state || $state === 'custom') {
                                                return;
                                            }

                                            $date = $get('date');
                                            if ($date) {
                                                $terms = PaymentTerms::parse($state);
                                                $set('expiry_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
                                            }
                                        }),
                                ])
                                    ->label('Variation Order date')
                                    ->columns(3),
                                Forms\Components\DatePicker::make('expiry_date')
                                    ->hidden(fn () => ! config('erp.show_expiry_date', true))
                                    ->label('Expiration date')
                                    ->nullable(),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount method')
                                    ->options(DocumentDiscountMethod::class)
                                    ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false))
                                    ->softRequired()
                                    ->default($settings?->discount_method ?? \App\Enums\Accounting\DocumentDiscountMethod::PerDocument)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        $discountMethod = DocumentDiscountMethod::parse($state);

                                        if ($discountMethod->isPerLineItem()) {
                                            $set('lineItemGroups.*.items.*.salesDiscounts', []);
                                        }

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
                    ]),

                Forms\Components\Section::make('Line Items')
                    ->schema([
                        Forms\Components\Repeater::make('lineItemGroups')
                            ->label('Item Groups')
                            ->relationship('lineItemGroups', function ($query) {
                                $query->with(['children' => function ($query) {
                                    $query->orderBy('order');
                                }, 'items' => function ($query) {
                                    $query->orderBy('line_number');
                                }])->orderBy('order');
                            })
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(true)
                            ->orderColumn('order')
                            ->defaultItems(1)
                            ->extraAttributes(['class' => 'item-group-darker'])
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\Hidden::make('offering_category_id'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Section Name (Optional)')
                                    ->hidden(fn (Forms\Get $get) => filled($get('offering_category_id')))
                                    ->dehydrated(true)
                                    ->dehydratedWhenHidden()
                                    ->placeholder('e.g. Materials, Labor')
                                    ->columnSpanFull(),

                                // Nested child groups (Sub-Groups)
                                Forms\Components\Repeater::make('children')
                                    ->relationship('children')
                                    ->saveRelationshipsUsing(null)
                                    ->dehydrated(true)
                                    ->orderColumn('order')
                                    ->label('Sub-Groups')
                                    ->hiddenLabel()
                                    ->visible(fn (Forms\Get $get) => filled($get('offering_category_id')))
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->schema([
                                        Forms\Components\Hidden::make('id'),
                                        Forms\Components\Hidden::make('offering_category_id'),
                                        Forms\Components\Hidden::make('parent_id'),
                                        Forms\Components\TextInput::make('name')
                                            ->label('Sub-Section Name')
                                            ->hidden(fn(Forms\Get $get) => filled($get('offering_category_id')))
                                            ->dehydrated(true)
                                            ->dehydratedWhenHidden()
                                            ->placeholder('e.g. Foundation, Framing')
                                            ->columnSpanFull(),
                                        CustomTableRepeater::make('items')
                                            ->hiddenLabel()
                                            ->minItems(0)
                                            ->emptyLabel(false)
                                            ->relationship()
                                            ->saveRelationshipsUsing(null)
                                            ->dehydrated(true)
                                            ->reorderable()
                                            ->orderColumn('line_number')
                                            ->reorderAtStart()
                                            ->cloneable()
                                            ->addActionLabel('Add an item')
                                            ->headers(function (Forms\Get $get) use ($settings) {
                                                $discountMethod = DocumentDiscountMethod::parse($get('../../../../discount_method'));
                                                $hasDiscounts = $discountMethod->isPerLineItem();

                                                $headers = [
                                                    Header::make($settings?->resolveColumnLabel('item_name', 'Items') ?? 'Items')
                                                        ->width('50%'),
                                                    Header::make('Unit')
                                                        ->width('7%')
                                                        ->markAsRequired(false),
                                                    Header::make($settings?->resolveColumnLabel('unit_name', 'Quantity') ?? 'Quantity')
                                                        ->width('8%'),
                                                    Header::make($settings?->resolveColumnLabel('price_name', 'Price') ?? 'Price')
                                                        ->width('10%'),
                                                ];

                                                if (! config('erp.hide_tax_and_adjustment_fields', false)) {
                                                    if ($hasDiscounts) {
                                                        $headers[] = Header::make('Adjustments')->width('15%');
                                                    } else {
                                                        $headers[] = Header::make('Taxes')->width('15%');
                                                    }
                                                }

                                                $headers[] = Header::make($settings?->resolveColumnLabel('amount_name', 'Amount') ?? 'Amount')
                                                    ->width('10%')
                                                    ->align('right');

                                                return $headers;
                                            })
                                            ->schema([
                                                Forms\Components\Hidden::make('id'),
                                                Forms\Components\Hidden::make('is_locked')
                                                    ->default(0),
                                                Forms\Components\Group::make([
                                                    CreateOfferingSelect::make('offering_id', true)
                                                        ->label('Item')
                                                        ->hiddenLabel()
                                                        ->placeholder('Select item')
                                                        ->required()
                                                        ->live()
                                                        ->inlineSuffix()
                                                        ->sellable()
                                                        ->options(function (Forms\Get $get) {
                                                            $categoryId = $get('../../offering_category_id') ?: $get('../../../../offering_category_id');
                                                            if (! $categoryId) {
                                                                return \App\Models\Common\Offering::pluck('name', 'id')->toArray();
                                                            }
                                                            $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);
                                                            return $category ? $category->offerings()->pluck('name', 'id')->toArray() : [];
                                                        })
                                                        ->searchable()
                                                        ->hidden(fn (Forms\Get $get) => $get('is_locked') >= 1)
                                                        ->dehydrated(true)
                                                        ->dehydratedWhenHidden(true)
                                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state, ?DocumentLineItem $record) {
                                                            $offeringId = $state;
                                                            $discountMethod = DocumentDiscountMethod::parse($get('../../../../../../discount_method'));
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

                                                            if (config('app.disable_custom_select_relationships', false)) {
                                                                return;
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
                                                        ->dehydrated(true)
                                                        ->hiddenLabel(),
                                                ])->columnSpan(1),
                                                Forms\Components\TextInput::make('unit')
                                                    ->placeholder('Unit')
                                                    ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                    ->dehydrated(true)
                                                    ->hiddenLabel(),
                                                Forms\Components\TextInput::make('quantity')
                                                    ->required()
                                                    ->numeric()
                                                    ->live()
                                                    ->maxValue(9999999999.99)
                                                    ->default(1),
                                                Forms\Components\TextInput::make('unit_price')
                                                    ->hiddenLabel()
                                                    ->money(useAffix: false)
                                                    ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                    ->dehydrated(true)
                                                    ->live()
                                                    ->default(0),
                                                Forms\Components\Group::make(config('erp.hide_tax_and_adjustment_fields', false) ? [] : [
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
                                                        ->multiple()
                                                        ->live()
                                                        ->disabled(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                        ->afterStateHydrated(static function (CreateAdjustmentSelect $component, ?\Illuminate\Database\Eloquent\Model $record) {
                                                            if ($record) {
                                                                $relation = $component->getAdjustmentsRelationship();
                                                                $component->state($record->{$relation}->pluck('id')->toArray());
                                                            }
                                                        })
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
                                                        ->disabled(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                        ->afterStateHydrated(static function (CreateAdjustmentSelect $component, ?\Illuminate\Database\Eloquent\Model $record) {
                                                            if ($record) {
                                                                $relation = $component->getAdjustmentsRelationship();
                                                                $component->state($record->{$relation}->pluck('id')->toArray());
                                                            }
                                                        })
                                                        ->hidden(function (Forms\Get $get) {
                                                            $discountMethod = DocumentDiscountMethod::parse($get('../../../../../../discount_method'));

                                                            return $discountMethod->isPerDocument();
                                                        })
                                                        ->searchable(),
                                                ])->columnSpan(1)
                                                  ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false)),
                                                Forms\Components\Placeholder::make('line_total_amount')
                                                    ->hiddenLabel()
                                                    ->dehydrated(true)
                                                    ->extraAttributes(['class' => 'text-left sm:text-right'])
                                                    ->content(function (Forms\Get $get) {
                                                        $quantity = max((float) ($get('quantity') ?? 0), 0);
                                                        $unitPrice = CurrencyConverter::isValidAmount($get('unit_price'), 'USD')
                                                            ? CurrencyConverter::convertToFloat($get('unit_price'), 'USD')
                                                            : 0;
                                                        $salesTaxes = $get('salesTaxes') ?? [];
                                                        $salesDiscounts = $get('salesDiscounts') ?? [];
                                                        $currencyCode = $get('../../../../../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                                                        $subtotal = $quantity * $unitPrice;

                                                        $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                                                        static $companyAdjustments = [];
                                                        $companyId = \Filament\Facades\Filament::getTenant()?->id ?? \Illuminate\Support\Facades\Auth::user()?->current_company_id ?? 1;
                                                        if (!isset($companyAdjustments[$companyId])) {
                                                            $companyAdjustments[$companyId] = Adjustment::where('company_id', $companyId)->get()->keyBy('id');
                                                        }

                                                        $taxAmountInCents = collect($salesTaxes)
                                                            ->map(fn($id) => $companyAdjustments[$companyId]->get($id))
                                                            ->filter()
                                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                                if ($adjustment->computation->isPercentage()) {
                                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                                } else {
                                                                    return $adjustment->getRawOriginal('rate');
                                                                }
                                                            });

                                                        $discountAmountInCents = collect($salesDiscounts)
                                                            ->map(fn($id) => $companyAdjustments[$companyId]->get($id))
                                                            ->filter()
                                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                                if ($adjustment->computation->isPercentage()) {
                                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                                } else {
                                                                    return $adjustment->getRawOriginal('rate');
                                                                }
                                                            });

                                                        // Final total
                                                        $totalInCents = $subtotalInCents + ($taxAmountInCents - $discountAmountInCents);

                                                        return CurrencyConverter::formatCentsToMoney($totalInCents, $currencyCode);
                                                    }),
                                            ])
                                            ->extraActions([
                                                Forms\Components\Actions\Action::make('add_job_scope')
                                                    ->label('Select Job Scope')
                                                    ->icon('heroicon-m-plus')
                                                    ->visible(fn (Forms\Get $get) => filled($get('offering_category_id')))
                                                    ->fillForm(function (Forms\Get $get) {
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);
                                                        $existingItems = $get('items') ?? [];
                                                        $existingOfferingIds = array_column($existingItems, 'offering_id');
                                                        $existingOfferingIds = array_map('strval', array_filter($existingOfferingIds));
                                                        
                                                        $preSelected = [];
                                                        if ($category) {
                                                            $ids = $category->offerings->pluck('id')
                                                                ->map('strval')
                                                                ->filter(fn($id) => in_array($id, $existingOfferingIds))
                                                                ->values()
                                                                ->toArray();
                                                            if (!empty($ids)) {
                                                                $preSelected = $ids;
                                                            }
                                                        }
                                                        
                                                        return [
                                                            'job_scopes' => $preSelected,
                                                        ];
                                                    })
                                                    ->form(function (Forms\Get $get) {
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::find($categoryId);
                                                        
                                                        $offerings = $category ? $category->offerings()->orderBy('sort_order')->get() : collect();

                                                        $schema = [];
                                                        
                                                        if ($offerings->isEmpty()) {
                                                            $schema[] = Forms\Components\Placeholder::make('no_options')
                                                                ->content('No offerings available for this category.');
                                                            return $schema;
                                                        }

                                                        $schema[] = Forms\Components\CheckboxList::make('job_scopes')
                                                            ->label('Select Offerings')
                                                            ->options($offerings->pluck('name', 'id')->toArray())
                                                            ->searchable()
                                                            ->bulkToggleable();

                                                        return $schema;
                                                    })
                                                    ->action(function (array $data, Forms\Set $set, Forms\Get $get) {
                                                        $selectedOfferingIds = $data['job_scopes'] ?? [];
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);
                                                        if (!$category) return;

                                                        $currentItems = $get('items') ?? [];
                                                        $existingOfferingIds = array_column($currentItems, 'offering_id');
                                                        $addedCount = 0;

                                                        foreach ($selectedOfferingIds as $offeringId) {
                                                            if (!in_array($offeringId, $existingOfferingIds)) {
                                                                $offering = $category->offerings->firstWhere('id', $offeringId);
                                                                if ($offering) {
                                                                    $currentItems[] = [
                                                                        'id' => null,
                                                                        'offering_id' => $offering->id,
                                                                        'description' => $offering->name,
                                                                        'is_locked' => 1,
                                                                        'unit' => $offering->unit,
                                                                        'quantity' => 1,
                                                                        'unit_price' => CurrencyConverter::convertCentsToFormatSimple($offering->price, 'USD'),
                                                                        'salesDiscounts' => [],
                                                                        'salesTaxes' => [],
                                                                    ];
                                                                    $addedCount++;
                                                                }
                                                            }
                                                        }

                                                        // Sort current items by offering sort_order
                                                        $allOfferingSortOrders = $category->offerings->pluck('sort_order', 'id')->toArray();
                                                        
                                                        usort($currentItems, function ($a, $b) use ($allOfferingSortOrders) {
                                                            $orderA = $allOfferingSortOrders[$a['offering_id']] ?? 0;
                                                            $orderB = $allOfferingSortOrders[$b['offering_id']] ?? 0;
                                                            
                                                            if ($orderA === $orderB) {
                                                                return strcmp($a['description'] ?? '', $b['description'] ?? '');
                                                            }
                                                            
                                                            return $orderA <=> $orderB;
                                                        });

                                                        $set('items', $currentItems);

                                                        if ($addedCount > 0) {
                                                            Notification::make()
                                                                ->title($addedCount . ' offerings added to this group')
                                                                ->success()
                                                                ->send();
                                                        }
                                                    }),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                                
                                // Items directly in group
                                CustomTableRepeater::make('items')
                                    ->hiddenLabel()
                                    ->minItems(0)
                                    ->emptyLabel(false)
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
                                            Header::make($settings?->resolveColumnLabel('item_name', 'Items') ?? 'Items')
                                                ->width('50%'),
                                            Header::make('Unit')
                                                ->width('7%')
                                                ->markAsRequired(false),
                                            Header::make($settings?->resolveColumnLabel('unit_name', 'Quantity') ?? 'Quantity')
                                                ->width('8%'),
                                            Header::make($settings?->resolveColumnLabel('price_name', 'Price') ?? 'Price')
                                                ->width('10%'),
                                        ];

                                        if (! config('erp.hide_tax_and_adjustment_fields', false)) {
                                            if ($hasDiscounts) {
                                                $headers[] = Header::make('Adjustments')->width('15%');
                                            } else {
                                                $headers[] = Header::make('Taxes')->width('15%');
                                            }
                                        }

                                        $headers[] = Header::make($settings?->resolveColumnLabel('amount_name', 'Amount') ?? 'Amount')
                                            ->width('10%')
                                            ->align('right');

                                        return $headers;
                                    })
                                    ->schema([
                                        Forms\Components\Hidden::make('id'),
                                        Forms\Components\Hidden::make('is_locked')
                                            ->default(0),
                                        Forms\Components\Group::make([
                                            CreateOfferingSelect::make('offering_id', true)
                                                ->label('Item')
                                                ->hiddenLabel()
                                                ->placeholder('Select item')
                                                ->required()
                                                ->live()
                                                ->inlineSuffix()
                                                ->sellable()
                                                ->options(function (Forms\Get $get) {
                                                    $categoryId = $get('../../offering_category_id') ?: $get('../../../../offering_category_id');
                                                    if (! $categoryId) {
                                                        return \App\Models\Common\Offering::pluck('name', 'id')->toArray();
                                                    }
                                                    $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);
                                                    return $category ? $category->offerings()->pluck('name', 'id')->toArray() : [];
                                                })
                                                ->searchable()
                                                ->hidden(fn (Forms\Get $get) => $get('is_locked') >= 1)
                                                ->dehydrated(true)
                                                ->dehydratedWhenHidden(true)
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

                                                    if (config('app.disable_custom_select_relationships', false)) {
                                                        return;
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
                                                ->dehydrated(true)
                                                ->hiddenLabel(),
                                        ])->columnSpan(1),
                                        Forms\Components\TextInput::make('unit')
                                            ->placeholder('Unit')
                                            ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                            ->dehydrated(true)
                                            ->hiddenLabel(),
                                        Forms\Components\TextInput::make('quantity')
                                            ->required()
                                            ->numeric()
                                            ->live()
                                            ->maxValue(9999999999.99)
                                            ->default(1),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->hiddenLabel()
                                            ->money(useAffix: false)
                                            ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                            ->dehydrated(true)
                                            ->live()
                                            ->default(0),
                                        Forms\Components\Group::make(config('erp.hide_tax_and_adjustment_fields', false) ? [] : [
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
                                                ->multiple()
                                                ->live()
                                                ->disabled(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                ->afterStateHydrated(static function (CreateAdjustmentSelect $component, ?\Illuminate\Database\Eloquent\Model $record) {
                                                    if ($record) {
                                                        $relation = $component->getAdjustmentsRelationship();
                                                        $component->state($record->{$relation}->pluck('id')->toArray());
                                                    }
                                                })
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
                                                ->disabled(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                ->afterStateHydrated(static function (CreateAdjustmentSelect $component, ?\Illuminate\Database\Eloquent\Model $record) {
                                                    if ($record) {
                                                        $relation = $component->getAdjustmentsRelationship();
                                                        $component->state($record->{$relation}->pluck('id')->toArray());
                                                    }
                                                })
                                                ->hidden(function (Forms\Get $get) {
                                                    $discountMethod = DocumentDiscountMethod::parse($get('../../../../discount_method'));

                                                    return $discountMethod->isPerDocument();
                                                })
                                                ->searchable(),
                                        ])->columnSpan(1)
                                          ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false)),
                                        Forms\Components\Placeholder::make('line_total_amount')
                                            ->hiddenLabel()
                                            ->dehydrated(true)
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
                                                $companyId = \Filament\Facades\Filament::getTenant()?->id ?? \Illuminate\Support\Facades\Auth::user()?->current_company_id ?? 1;
                                                if (!isset($companyAdjustments[$companyId])) {
                                                    $companyAdjustments[$companyId] = Adjustment::where('company_id', $companyId)->get()->keyBy('id');
                                                }

                                                $taxAmountInCents = collect($salesTaxes)
                                                    ->map(fn($id) => $companyAdjustments[$companyId]->get($id))
                                                    ->filter()
                                                    ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                        if ($adjustment->computation->isPercentage()) {
                                                            return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                        } else {
                                                            return $adjustment->getRawOriginal('rate');
                                                        }
                                                    });

                                                $discountAmountInCents = collect($salesDiscounts)
                                                    ->map(fn($id) => $companyAdjustments[$companyId]->get($id))
                                                    ->filter()
                                                    ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                        if ($adjustment->computation->isPercentage()) {
                                                            return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                        } else {
                                                            return $adjustment->getRawOriginal('rate');
                                                        }
                                                    });

                                                // Final total
                                                $totalInCents = $subtotalInCents + ($taxAmountInCents - $discountAmountInCents);

                                                return CurrencyConverter::formatCentsToMoney($totalInCents, $currencyCode);
                                            }),
                                    ])
                                    ->extraActions([
                                        Forms\Components\Actions\Action::make('add_job_scope')
                                            ->label('Select Job Scope')
                                            ->icon('heroicon-m-plus')
                                            ->visible(fn (Forms\Get $get) => filled($get('offering_category_id')))
                                            ->fillForm(function (Forms\Get $get) {
                                                $categoryId = $get('offering_category_id');
                                                $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);
                                                $existingItems = $get('items') ?? [];
                                                $existingOfferingIds = array_column($existingItems, 'offering_id');
                                                $existingOfferingIds = array_map('strval', array_filter($existingOfferingIds));
                                                
                                                $preSelected = [];
                                                if ($category) {
                                                    $ids = $category->offerings->pluck('id')
                                                        ->map('strval')
                                                        ->filter(fn($id) => in_array($id, $existingOfferingIds))
                                                        ->values()
                                                        ->toArray();
                                                    if (!empty($ids)) {
                                                        $preSelected = $ids;
                                                    }
                                                }
                                                
                                                return [
                                                    'job_scopes' => $preSelected,
                                                ];
                                            })
                                            ->form(function (Forms\Get $get) {
                                                $categoryId = $get('offering_category_id');
                                                $category = \App\Models\Common\OfferingCategory::find($categoryId);
                                                
                                                $offerings = $category ? $category->offerings()->orderBy('sort_order')->get() : collect();

                                                $schema = [];
                                                
                                                if ($offerings->isEmpty()) {
                                                    $schema[] = Forms\Components\Placeholder::make('no_options')
                                                        ->content('No offerings available for this category.');
                                                    return $schema;
                                                }

                                                $schema[] = Forms\Components\CheckboxList::make('job_scopes')
                                                    ->label('Select Offerings')
                                                    ->options($offerings->pluck('name', 'id')->toArray())
                                                    ->searchable()
                                                    ->bulkToggleable();

                                                return $schema;
                                            })
                                            ->action(function (array $data, Forms\Set $set, Forms\Get $get) {
                                                $selectedOfferingIds = $data['job_scopes'] ?? [];
                                                $categoryId = $get('offering_category_id');
                                                $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);
                                                if (!$category) return;

                                                $currentItems = $get('items') ?? [];
                                                $existingOfferingIds = array_column($currentItems, 'offering_id');
                                                $addedCount = 0;

                                                foreach ($selectedOfferingIds as $offeringId) {
                                                    if (!in_array($offeringId, $existingOfferingIds)) {
                                                        $offering = $category->offerings->firstWhere('id', $offeringId);
                                                        if ($offering) {
                                                            $currentItems[] = [
                                                                'id' => null,
                                                                'offering_id' => $offering->id,
                                                                'description' => $offering->name,
                                                                'is_locked' => 1,
                                                                'unit' => $offering->unit,
                                                                'quantity' => 1,
                                                                'unit_price' => CurrencyConverter::convertCentsToFormatSimple($offering->price, 'USD'),
                                                                'salesDiscounts' => [],
                                                                'salesTaxes' => [],
                                                            ];
                                                            $addedCount++;
                                                        }
                                                    }
                                                }

                                                // Sort current items by offering sort_order
                                                $allOfferingSortOrders = $category->offerings->pluck('sort_order', 'id')->toArray();
                                                
                                                usort($currentItems, function ($a, $b) use ($allOfferingSortOrders) {
                                                    $orderA = $allOfferingSortOrders[$a['offering_id']] ?? 0;
                                                    $orderB = $allOfferingSortOrders[$b['offering_id']] ?? 0;
                                                    
                                                    if ($orderA === $orderB) {
                                                        return strcmp($a['description'] ?? '', $b['description'] ?? '');
                                                    }
                                                    
                                                    return $orderA <=> $orderB;
                                                });

                                                $set('items', $currentItems);

                                                if ($addedCount > 0) {
                                                    Notification::make()
                                                        ->title($addedCount . ' offerings added to this group')
                                                        ->success()
                                                        ->send();
                                                }
                                            }),
                                    ]),
                            ]),
                        DocumentTotals::make()
                            ->type(DocumentType::VariationOrder),
                        Forms\Components\Select::make('template_company_id')
                            ->label('Issue Company')
                            ->relationship(
                                name: 'templateCompany',
                                titleAttribute: 'name',
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $company = \App\Models\Company::with('profile')->find($state);
                                $defaultTaxId = $company?->profile?->default_sales_tax_id;

                                if ($defaultTaxId) {
                                    $set('salesTaxes', [$defaultTaxId]);
                                } else {
                                    $set('salesTaxes', []);
                                }
                            }),
                        Forms\Components\Textarea::make('terms')
                            ->default($settings?->terms)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->default($settings?->notes)
                            ->columnSpanFull(),
                    ]),
                DocumentFooterSection::make('Variation Order Footer')
                    ->defaultFooter($settings?->footer),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->isNotTemplate())
            ->defaultSort('date', 'desc')
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vo_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expiration date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->hidden(fn () => ! config('erp.show_expiry_date', true)),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn (VariationOrder $record) => $record->currency_code)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(VariationOrderStatus::class)
                    ->native(false),
                DateRangeFilter::make('date')
                    ->fromLabel('From date')
                    ->untilLabel('To date')
                    ->indicatorLabel('Date'),
                DateRangeFilter::make('expiry_date')
                    ->fromLabel('From expiration date')
                    ->untilLabel('To expiration date')
                    ->indicatorLabel('Expiration date')
                    ->hidden(fn () => ! config('erp.show_expiry_date', true)),

            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\EditAction::make(),
                        Tables\Actions\ViewAction::make(),
                        VariationOrder::getPreviewAction(Tables\Actions\Action::class),
                        VariationOrder::getReplicateAction(Tables\Actions\ReplicateAction::class),
                        VariationOrder::getApproveDraftAction(Tables\Actions\Action::class),
                        VariationOrder::getMarkAsSentAction(Tables\Actions\Action::class),
                        VariationOrder::getMarkAsRejectedAction(Tables\Actions\Action::class),
                    ])->dropdown(false),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVariationOrders::route('/'),
            'create' => Pages\CreateVariationOrder::route('/create'),
            'view'   => Pages\ViewVariationOrder::route('/{record}'),
            'edit'   => Pages\EditVariationOrder::route('/{record}/edit'),
        ];
    }
}

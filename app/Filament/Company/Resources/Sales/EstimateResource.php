<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentStatus;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Setting\PaymentTerms;
use App\Filament\Company\Resources\Sales\ClientResource\RelationManagers\EstimatesRelationManager;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages;
use App\Filament\Company\Resources\Sales\EstimateResource\Widgets;
use App\Filament\Exports\Accounting\EstimateExporter;
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
use App\Models\Accounting\Estimate;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class EstimateResource extends Resource
{
    protected static ?string $model = Estimate::class;

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        $settings = $company->defaultEstimate;

        return $form
            ->schema([
                DocumentHeaderSection::make('Estimate Header')
                    ->defaultHeader($settings->header)
                    ->defaultSubheader($settings->subheader),
                Forms\Components\Section::make('Estimate Details')
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
                                CreateCurrencySelect::make('currency_code'),
                            ]),
                            Forms\Components\Group::make([
                                Forms\Components\TextInput::make('estimate_number')
                                    ->label('Estimate number')
                                    ->default(static fn () => Estimate::getNextDocumentNumber()),
                                Forms\Components\TextInput::make('reference_number')
                                    ->label('Reference number'),
                                Cluster::make([
                                    Forms\Components\DatePicker::make('date')
                                        ->label('Estimate date')
                                        ->live()
                                        ->default(company_today()->toDateString())
                                        ->columnSpan(2)
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                            $date = Carbon::parse($state)->toDateString();
                                            $expirationDate = Carbon::parse($get('expiration_date'))->toDateString();

                                            if ($date && $expirationDate && $date > $expirationDate) {
                                                $set('expiration_date', $date);
                                            }

                                            $paymentTerms = $get('payment_terms');
                                            if ($date && $paymentTerms && $paymentTerms !== 'custom') {
                                                $terms = PaymentTerms::parse($paymentTerms);
                                                $set('expiration_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
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
                                                $set('expiration_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
                                            }
                                        }),
                                ])
                                    ->label('Estimate date')
                                    ->columns(3),
                                Forms\Components\DatePicker::make('expiration_date')
                                    ->label('Expiration date')
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

                                        $date = $get('date');
                                        $paymentTerms = $get('payment_terms');

                                        if (! $date || $paymentTerms === 'custom') {
                                            return;
                                        }

                                        $term = PaymentTerms::parse($paymentTerms);
                                        $expected = Carbon::parse($date)->addDays($term->getDays());

                                        if (! Carbon::parse($state)->isSameDay($expected)) {
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
                            ])->grow(true),
                        ])->from('md'),
                        Forms\Components\Repeater::make('lineItemGroups')
                            ->extraAttributes(['class' => 'item-group-darker'])
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
                                        Forms\Components\Hidden::make('offering_category_id'),
                                        Forms\Components\TextInput::make('name')
                                            ->label('Section Name (Optional)')
                                            ->hidden(fn (Forms\Get $get) => filled($get('offering_category_id')))
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
                                                Forms\Components\Hidden::make('is_locked')
                                                    ->default(0),
                                                Forms\Components\Group::make([
                                                    CreateOfferingSelect::make('offering_id')
                                                        ->label('Item')
                                                        ->hiddenLabel()
                                                        ->placeholder('Select item')
                                                        ->required()
                                                        ->live()
                                                        ->inlineSuffix()
                                                        ->sellable()
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
                                                Forms\Components\Group::make([
                                                    CreateAdjustmentSelect::make('salesTaxes')
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
                                                        ->disabled(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                        ->searchable(),
                                                    CreateAdjustmentSelect::make('salesDiscounts')
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

                                                        $taxAmountInCents = Adjustment::whereIn('id', $salesTaxes)
                                                            ->get()
                                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                                if ($adjustment->computation->isPercentage()) {
                                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                                } else {
                                                                    return $adjustment->getRawOriginal('rate');
                                                                }
                                                            });

                                                        $discountAmountInCents = Adjustment::whereIn('id', $salesDiscounts)
                                                            ->get()
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
                                                        $category = \App\Models\Common\OfferingCategory::with('children.offerings')->find($categoryId);
                                                        $existingItems = $get('items') ?? [];
                                                        $existingOfferingIds = array_column($existingItems, 'offering_id');
                                                        $existingOfferingIds = array_map('strval', array_filter($existingOfferingIds));
                                                        
                                                        $preSelected = [];
                                                        if ($category) {
                                                            foreach ($category->children as $child) {
                                                                $ids = $child->offerings->pluck('id')
                                                                    ->map('strval')
                                                                    ->filter(fn($id) => in_array($id, $existingOfferingIds))
                                                                    ->values()
                                                                    ->toArray();
                                                                if (!empty($ids)) {
                                                                    $preSelected[$child->id] = $ids;
                                                                }
                                                            }
                                                        }
                                                        
                                                        return [
                                                            'job_scopes_grouped' => $preSelected,
                                                        ];
                                                    })
                                                    ->form(function (Forms\Get $get) {
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::find($categoryId);
                                                        
                                                        // Get all children categories (Job Scope Descriptions)
                                                        // And their offerings (Job Scope Options)
                                                        $jobScopeDescriptions = $category ? $category->children()->with('offerings')->get() : collect();

                                                        $schema = [];
                                                        
                                                        // Add Search Input
                                                        $schema[] = Forms\Components\TextInput::make('search_job_scopes')
                                                            ->label('Search')
                                                            ->placeholder('Search job scopes...')
                                                            ->prefixIcon('heroicon-m-magnifying-glass')
                                                            ->live(debounce: 300);

                                                        if ($jobScopeDescriptions->isEmpty()) {
                                                            $schema[] = Forms\Components\Placeholder::make('no_options')
                                                                ->content('No Job Scopes available for this category.');
                                                            return $schema;
                                                        }

                                                        foreach ($jobScopeDescriptions as $description) {
                                                            // Pass strict variables to closures
                                                            $descriptionName = $description->name;
                                                            $descriptionId = $description->id;
                                                            // We need to pass the offerings data (id => name) or the collection to the closure
                                                            // But the closure needs to filter it.
                                                            // To avoid serializing large objects, let's pass a simple array of [id, name]
                                                            $allOfferings = $description->offerings->map(fn($o) => ['id' => (string)$o->id, 'name' => $o->name])->values()->toArray();

                                                            if (empty($allOfferings)) {
                                                                continue;
                                                            }

                                                            $schema[] = Forms\Components\Section::make($descriptionName)
                                                                ->schema([
                                                                    Forms\Components\CheckboxList::make("job_scopes_grouped.{$descriptionId}")
                                                                        ->hiddenLabel()
                                                                        ->searchable(false)
                                                                        ->bulkToggleable()
                                                                        ->options(function (Forms\Get $get) use ($allOfferings) {
                                                                            $term = $get('search_job_scopes');
                                                                            
                                                                            $filtered = collect($allOfferings);
                                                                            if (filled($term)) {
                                                                                $filtered = $filtered->filter(function ($item) use ($term) {
                                                                                    return \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term));
                                                                                });
                                                                            }
                                                                            
                                                                            return $filtered->pluck('name', 'id')->toArray();
                                                                        }),
                                                                ])
                                                                ->collapsible()
                                                                ->compact()
                                                                ->visible(function (Forms\Get $get) use ($allOfferings) {
                                                                    $term = $get('search_job_scopes');
                                                                    if (blank($term)) {
                                                                        return true;
                                                                    }
                                                                    // Check if any offering matches
                                                                    return collect($allOfferings)->contains(function ($item) use ($term) {
                                                                        return \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term));
                                                                    });
                                                                });
                                                        }

                                                        return $schema;
                                                    })
                                                    ->action(function (array $data, Forms\Set $set, Forms\Get $get, $component) use ($company) {
                                                        // 1. Get ALL selected IDs from the grouped checkboxes
                                                        $groupedData = $data['job_scopes_grouped'] ?? [];
                                                        $selectedOfferingIds = [];
                                                        foreach ($groupedData as $groupId => $ids) {
                                                            if (is_array($ids)) {
                                                                $selectedOfferingIds = array_merge($selectedOfferingIds, $ids);
                                                            }
                                                        }
                                                        $selectedOfferingIds = array_unique(array_filter($selectedOfferingIds));

                                                        // 2. Identify what was VISIBLE based on search
                                                        $searchTerm = $data['search_job_scopes'] ?? '';
                                                        
                                                        $parentCategoryId = $get('offering_category_id');
                                                        $parentCategory = \App\Models\Common\OfferingCategory::with('children.offerings')->find($parentCategoryId);
                                                        if (!$parentCategory) return;

                                                        $managedOfferings = $parentCategory->children->flatMap->offerings;
                                                        $managedOfferingIds = $managedOfferings->pluck('id')->toArray();
                                                        
                                                        // Create a map of offering_id => index for sorting
                                                        $offeringSortMap = [];
                                                        foreach ($managedOfferings->values() as $index => $o) {
                                                            $offeringSortMap[$o->id] = $index;
                                                        }

                                                        // Which managed offerings were visible in the last view?
                                                        $visibleManagedOfferingIds = $managedOfferings->filter(function($o) use ($searchTerm) {
                                                            if (blank($searchTerm)) return true;
                                                            return \Illuminate\Support\Str::contains(strtolower($o->name), strtolower($searchTerm));
                                                        })->pluck('id')->toArray();

                                                        // 3. Process current items
                                                        $currentItems = $get('items') ?? [];
                                                        $newItems = [];
                                                        $existingOfferingIdsInList = [];

                                                        foreach ($currentItems as $item) {
                                                            $offeringId = $item['offering_id'] ?? null;
                                                            
                                                            if ($offeringId && in_array($offeringId, $managedOfferingIds)) {
                                                                // It's a managed item. 
                                                                // Should we keep it?
                                                                $isVisible = in_array($offeringId, $visibleManagedOfferingIds);
                                                                $isSelected = in_array((string)$offeringId, $selectedOfferingIds);

                                                                if ($isVisible) {
                                                                    // If it's visible, the checkbox is the source of truth
                                                                    if ($isSelected) {
                                                                        $offering = $managedOfferings->firstWhere('id', $offeringId);
                                                                        $item['_sort_scope_lft'] = $offering->categories->firstWhere('id', '!=', $parentCategoryId)?->getRawOriginal('_scope_lft') ?? 900000;
                                                                        $item['_sort_offering_index'] = $offeringSortMap[$offeringId] ?? 999999;
                                                                        $item['_sort_name'] = $offering->name;
                                                                        $newItems[] = $item;
                                                                        $existingOfferingIdsInList[] = $offeringId;
                                                                    }
                                                                    // If visible but NOT selected -> skip (remove)
                                                                } else {
                                                                    // If it was hidden, keep its current state in the list
                                                                    $offering = $managedOfferings->firstWhere('id', $offeringId);
                                                                    $item['_sort_scope_lft'] = $offering->categories->firstWhere('id', '!=', $parentCategoryId)?->getRawOriginal('_scope_lft') ?? 900000;
                                                                    $item['_sort_offering_index'] = $offeringSortMap[$offeringId] ?? 999999;
                                                                    $item['_sort_name'] = $offering->name;
                                                                    $newItems[] = $item;
                                                                    $existingOfferingIdsInList[] = $offeringId;
                                                                }
                                                            } else {
                                                                // It's a custom item -> Always keep
                                                                $newItems[] = $item;
                                                            }
                                                        }

                                                        // 4. Add missing NEWLY selected items
                                                        $addedCount = 0;
                                                        foreach ($selectedOfferingIds as $offeringId) {
                                                            if (!in_array($offeringId, array_map('strval', $existingOfferingIdsInList))) {
                                                                $offering = $managedOfferings->firstWhere('id', $offeringId);
                                                                if ($offering) {
                                                                    $newItems[] = [
                                                                        'id' => null,
                                                                        'offering_id' => $offering->id,
                                                                        'description' => $offering->name,
                                                                        'is_locked' => 1,
                                                                        'uom' => $offering->uom,
                                                                        'qty' => 1,
                                                                        'unit_price' => $offering->price,
                                                                        'salesDiscounts' => [],
                                                                        'salesTaxes' => [],
                                                                        '_sort_scope_lft' => $offering->categories->firstWhere('id', '!=', $parentCategoryId)?->getRawOriginal('_scope_lft') ?? 900000,
                                                                        '_sort_offering_index' => $offeringSortMap[$offeringId] ?? 999999,
                                                                        '_sort_name' => $offering->name,
                                                                    ];
                                                                    $addedCount++;
                                                                }
                                                            }
                                                        }

                                                        // 5. Sort
                                                        usort($newItems, function($a, $b) {
                                                            if (!isset($a['_sort_scope_lft']) && !isset($b['_sort_scope_lft'])) return 0;
                                                            if (!isset($a['_sort_scope_lft'])) return 1;
                                                            if (!isset($b['_sort_scope_lft'])) return -1;

                                                            if ($a['_sort_scope_lft'] !== $b['_sort_scope_lft']) {
                                                                return $a['_sort_scope_lft'] <=> $b['_sort_scope_lft'];
                                                            }
                                                            if ($a['_sort_offering_index'] !== $b['_sort_offering_index']) {
                                                                return $a['_sort_offering_index'] <=> $b['_sort_offering_index'];
                                                            }
                                                            return strcasecmp($a['_sort_name'], $b['_sort_name']);
                                                        });

                                                        $set('items', $newItems);
                                                        
                                                        if ($addedCount > 0) {
                                                            Notification::make()
                                                                ->title($addedCount . ' job scope items updated')
                                                                ->success()
                                                                ->send();
                                                        }
                                                    }),
                                            ]),
                                    ]),
                        DocumentTotals::make()
                            ->type(DocumentType::Estimate),
                        Forms\Components\Textarea::make('terms')
                            ->default($settings->terms)
                            ->columnSpanFull(),
                    ]),
                DocumentFooterSection::make('Estimate Footer')
                    ->defaultFooter($settings->footer),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expiration_date')
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Expiration date')
                    ->asRelativeDay()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimate_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable()
                    ->hiddenOn(EstimatesRelationManager::class),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn (Estimate $record) => $record->currency_code)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->hiddenOn(EstimatesRelationManager::class),
                Tables\Filters\SelectFilter::make('status')
                    ->options(EstimateStatus::class)
                    ->native(false),
                DateRangeFilter::make('date')
                    ->fromLabel('From date')
                    ->untilLabel('To date')
                    ->indicatorLabel('Date'),
                DateRangeFilter::make('expiration_date')
                    ->fromLabel('From expiration date')
                    ->untilLabel('To expiration date')
                    ->indicatorLabel('Due'),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(EstimateExporter::class),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ActionGroup::make([
                        Tables\Actions\EditAction::make()
                            ->url(static fn (Estimate $record) => Pages\EditEstimate::getUrl(['record' => $record])),
                        Tables\Actions\ViewAction::make()
                            ->url(static fn (Estimate $record) => Pages\ViewEstimate::getUrl(['record' => $record])),
                        Estimate::getReplicateAction(Tables\Actions\ReplicateAction::class),
                        Estimate::getApproveDraftAction(Tables\Actions\Action::class),
                        Estimate::getMarkAsSentAction(Tables\Actions\Action::class),
                        Estimate::getMarkAsAcceptedAction(Tables\Actions\Action::class),
                        Estimate::getMarkAsDeclinedAction(Tables\Actions\Action::class),
                        Estimate::getConvertToInvoiceAction(Tables\Actions\Action::class),
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
                        ->modalDescription('Replicating estimates will also replicate their line items. Are you sure you want to proceed?')
                        ->successNotificationTitle('Estimates replicated successfully')
                        ->failureNotificationTitle('Failed to replicate estimates')
                        ->databaseTransaction()
                        ->deselectRecordsAfterCompletion()
                        ->excludeAttributes([
                            'estimate_number',
                            'date',
                            'expiration_date',
                            'approved_at',
                            'accepted_at',
                            'converted_at',
                            'declined_at',
                            'last_sent_at',
                            'last_viewed_at',
                            'status',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ])
                        ->beforeReplicaSaved(function (Estimate $replica) {
                            $replica->status = EstimateStatus::Draft;
                            $replica->estimate_number = Estimate::getNextDocumentNumber();
                            $replica->date = company_today();
                            $replica->expiration_date = company_today()->addDays($replica->company->defaultInvoice->payment_terms->getDays());
                        })
                        ->afterReplicaSaved(function (Estimate $original, Estimate $replica) {
                            $original->replicateLineItems($replica);
                        }),

                    Tables\Actions\BulkAction::make('approveDrafts')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates approved')
                        ->failureNotificationTitle('Failed to approve estimates')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeApproved());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Approval failed')
                                    ->body('Only draft estimates can be approved. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->approveDraft();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsSent')
                        ->label('Mark as sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates sent')
                        ->failureNotificationTitle('Failed to mark estimates as sent')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeMarkedAsSent());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Sending failed')
                                    ->body('Only unsent estimates can be marked as sent. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsSent();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsAccepted')
                        ->label('Mark as accepted')
                        ->icon('heroicon-o-check-badge')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates accepted')
                        ->failureNotificationTitle('Failed to mark estimates as accepted')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeMarkedAsAccepted());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Acceptance failed')
                                    ->body('Only sent estimates that haven\'t been accepted can be marked as accepted. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsAccepted();
                            });

                            $action->success();
                        }),
                    Tables\Actions\BulkAction::make('markAsDeclined')
                        ->label('Mark as declined')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->databaseTransaction()
                        ->color('danger')
                        ->modalHeading('Mark Estimates as Declined')
                        ->modalDescription('Are you sure you want to mark the selected estimates as declined? This action cannot be undone.')
                        ->successNotificationTitle('Estimates declined')
                        ->failureNotificationTitle('Failed to mark estimates as declined')
                        ->before(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn (Estimate $record) => ! $record->canBeMarkedAsDeclined());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Declination failed')
                                    ->body('Only sent estimates that haven\'t been declined can be marked as declined. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, Tables\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsDeclined();
                            });

                            $action->success();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEstimates::route('/'),
            'create' => Pages\CreateEstimate::route('/create'),
            'view' => Pages\ViewEstimate::route('/{record}'),
            'edit' => Pages\EditEstimate::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\EstimateOverview::class,
        ];
    }
}

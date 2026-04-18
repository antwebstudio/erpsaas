<?php

namespace App\Filament\Company\Resources\Sales;

use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Models\Accounting\Estimate;

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentStatus;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EstimateStatus;
use App\Enums\Setting\PaymentTerms;
use App\Filament\Company\Resources\Sales\EstimateTemplateResource\Pages;
use App\Filament\Company\Resources\Sales\EstimateTemplateResource\RelationManagers;
use App\Filament\Forms\Components\CreateAdjustmentSelect;
use App\Filament\Forms\Components\CreateOfferingSelect;
use App\Filament\Forms\Components\CustomTableRepeater;
use App\Filament\Tables\Columns;
use App\Filament\Forms\Components\DocumentFooterSection;
use App\Filament\Forms\Components\DocumentTotals;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\EstimateTemplate;
use App\Models\Common\Offering;
use App\Models\Common\OfferingCategory;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EstimateTemplateResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = EstimateTemplate::class;

    public static function shouldRegisterNavigation(): bool
    {
        if (config('erp.hide_estimate_in_navigation', false)) {
            return false;
        }

        return static::canViewAny();
    }


    protected static ?string $slug = 'sales/estimate-templates';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Estimate Templates';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->isTemplate();
    }

    public static function form(Form $form): Form
    {
        $company = Auth::user()->currentCompany;

        $settings = $company->defaultEstimate;

        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->schema([
                        Forms\Components\TextInput::make('header')
                            ->label('Template Name')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('subheader')
                            ->label('Template Description')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('discount_method')
                            ->label('Discount method')
                            ->options(DocumentDiscountMethod::class)
                            ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false))
                            ->softRequired()
                            ->default($settings->discount_method)
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
                        Forms\Components\Hidden::make('is_template')
                            ->default(true),
                    ]),
                Forms\Components\Section::make('Estimate Items')
                    ->schema([
                        Forms\Components\Repeater::make('lineItemGroups')
                            ->extraAttributes(['class' => 'item-group-darker'])
                            ->relationship('lineItemGroups', function ($query) {
                                return $query->whereNull('parent_id');
                            })
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(true)
                            ->orderColumn('order')
                            ->defaultItems(0)
                            ->label('Item Groups')
                            ->hiddenLabel()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\Hidden::make('offering_category_id'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Section Name (Optional)')
                                    ->hidden(fn (Forms\Get $get) => filled($get('offering_category_id')) && ! config('erp.allow_edit_group_header', false))
                                    ->dehydrated(true)
                                    ->dehydratedWhenHidden()
                                    ->placeholder('e.g. Materials, Labor')
                                    ->columnSpanFull(),
                                
                                // Original items repeater for parent groups without children
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
                                            ->addable(fn (Forms\Get $get) => !(filled($get('offering_category_id')) && config('erp.hide_add_item_for_group', false)))
                                            ->headers(function (Forms\Get $get) use ($settings) {
                                                $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                                $hasDiscounts = $discountMethod->isPerLineItem();

                                                $headers = [
                                                    Header::make($settings->resolveColumnLabel('item_name', 'Items'))
                                                        ->width('50%'),
                                                    Header::make('Unit')
                                                        ->width('7%')
                                                        ->markAsRequired(false),
                                                    Header::make($settings->resolveColumnLabel('unit_name', 'Quantity'))
                                                        ->width('8%'),
                                                    Header::make($settings->resolveColumnLabel('price_name', 'Price'))
                                                        ->width('10%'),
                                                ];

                                                if (! config('erp.hide_tax_and_adjustment_fields', false)) {
                                                    if ($hasDiscounts) {
                                                        $headers[] = Header::make('Adjustments')->width('15%');
                                                    } else {
                                                        $headers[] = Header::make('Taxes')->width('15%');
                                                    }
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
                                                        ->required(fn (Forms\Get $get) => filled($get('offering_id')))
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
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
                                                    ->numeric()
                                                    ->live(onBlur: true)
                                                    ->maxValue(9999999999.99)
                                                    ->default(1),
                                                Forms\Components\TextInput::make('unit_price')
                                                    ->hiddenLabel()
                                                    ->money(useAffix: false)
                                                    ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                    ->dehydrated(true)
                                                    ->live(onBlur: true)
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
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
                                                ])->columnSpan(1)
                                                  ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false)),
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
                                                        $currencyCode = CurrencyAccessor::getDefaultCurrency();

                                                        $subtotal = $quantity * $unitPrice;

                                                        $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                                                        static $companyAdjustments = [];
                                                        $companyId = auth()->user()->current_company_id;

                                                        if (! isset($companyAdjustments[$companyId])) {
                                                            $companyAdjustments[$companyId] = Adjustment::where('company_id', $companyId)->get()->keyBy('id');
                                                        }

                                                        $taxAmountInCents = collect($salesTaxes)
                                                            ->map(fn ($id) => $companyAdjustments[$companyId]->get($id))
                                                            ->filter()
                                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                                if ($adjustment->computation->isPercentage()) {
                                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                                } else {
                                                                    return $adjustment->getRawOriginal('rate');
                                                                }
                                                            });

                                                        $discountAmountInCents = collect($salesDiscounts)
                                                            ->map(fn ($id) => $companyAdjustments[$companyId]->get($id))
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
                                                    ->button()
                                                    ->color('primary')
                                                    ->icon('heroicon-m-plus')
                                                    ->visible(fn (Forms\Get $get) => filled($get('offering_category_id')))
                                                    ->fillForm(function (Forms\Get $get) {
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::with(['children.offerings', 'offerings'])->find($categoryId);
                                                        
                                                        // Gather all existing offering IDs in this group (main items + sub-group items)
                                                        $existingItems = $get('items') ?? [];
                                                        $existingOfferingIds = array_column($existingItems, 'offering_id');
                                                        
                                                        $existingChildren = $get('children') ?? [];
                                                        foreach ($existingChildren as $child) {
                                                            $childItems = $child['items'] ?? [];
                                                            $childOfferingIds = array_column($childItems, 'offering_id');
                                                            $existingOfferingIds = array_merge($existingOfferingIds, $childOfferingIds);
                                                        }
                                                        
                                                        $existingOfferingIds = array_unique(array_map('strval', array_filter($existingOfferingIds)));
                                                        
                                                        $preSelectedGrouped = [];
                                                        $preSelectedRoot = [];
                                                        if ($category) {
                                                            $preSelectedRoot = $category->offerings->pluck('id')
                                                                ->map('strval')
                                                                ->filter(fn($id) => in_array($id, $existingOfferingIds))
                                                                ->values()
                                                                ->toArray();

                                                            foreach ($category->children as $child) {
                                                                $ids = $child->offerings->pluck('id')
                                                                    ->map('strval')
                                                                    ->filter(fn($id) => in_array($id, $existingOfferingIds))
                                                                    ->values()
                                                                    ->toArray();
                                                                if (!empty($ids)) {
                                                                    $preSelectedGrouped[$child->id] = $ids;
                                                                }
                                                            }
                                                        }
                                                        
                                                        return [
                                                            'job_scopes_grouped' => $preSelectedGrouped,
                                                            'job_scopes_root' => $preSelectedRoot,
                                                        ];
                                                    })
                                                    ->form(function (Forms\Get $get) {
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);
                                                        
                                                        // Get all children categories (Job Scope Descriptions)
                                                        // And their offerings (Job Scope Options)
                                                        $jobScopeDescriptions = $category ? $category->children()->with('offerings')->get() : collect();
                                                        $rootOfferings = $category ? $category->offerings()
                                                            ->orderBy('sort_order')
                                                            ->orderBy('name')
                                                            ->get()
                                                            ->map(fn($o) => ['id' => (string)$o->id, 'name' => $o->name, 'sort_order' => $o->sort_order])
                                                            ->values()
                                                            ->toArray() : [];

                                                        $schema = [];
                                                        
                                                        // Add Search Input
                                                        $schema[] = Forms\Components\TextInput::make('search_job_scopes')
                                                            ->label('Search')
                                                            ->placeholder('Search job scopes...')
                                                            ->prefixIcon('heroicon-m-magnifying-glass')
                                                            ->live(debounce: 300);

                                                        if ($jobScopeDescriptions->isEmpty() && empty($rootOfferings)) {
                                                            $schema[] = Forms\Components\Placeholder::make('no_options')
                                                                ->content('No Job Scopes available for this category.');
                                                            return $schema;
                                                        }

                                                        if (!empty($rootOfferings)) {
                                                            $schema[] = Forms\Components\Section::make('General')
                                                                ->schema([
                                                                    Forms\Components\CheckboxList::make("job_scopes_root")
                                                                        ->hiddenLabel()
                                                                        ->extraAttributes(['class' => 'job-scope-checkbox-list'])
                                                                        ->searchable(false)
                                                                        ->bulkToggleable()
                                                                        ->options(function (Forms\Get $get) use ($rootOfferings) {
                                                                            $term = $get('search_job_scopes');
                                                                            
                                                                            $filtered = collect($rootOfferings);
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
                                                                ->visible(function (Forms\Get $get) use ($rootOfferings) {
                                                                    $term = $get('search_job_scopes');
                                                                    if (blank($term)) {
                                                                        return true;
                                                                    }
                                                                    // Check if any offering matches
                                                                    return collect($rootOfferings)->contains(function ($item) use ($term) {
                                                                        return \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term));
                                                                    });
                                                                });
                                                        }

                                                        foreach ($jobScopeDescriptions as $description) {
                                                            // Pass strict variables to closures
                                                            $descriptionName = $description->name;
                                                            $descriptionId = $description->id;
                                                            $allOfferings = $description->offerings()
                                                                ->orderBy('sort_order')
                                                                ->orderBy('name')
                                                                ->get()
                                                                ->map(fn($o) => ['id' => (string)$o->id, 'name' => $o->name, 'sort_order' => $o->sort_order])
                                                                ->values()
                                                                ->toArray();

                                                            if (empty($allOfferings)) {
                                                                continue;
                                                            }

                                                            $schema[] = Forms\Components\Section::make($descriptionName)
                                                                ->schema([
                                                                    Forms\Components\CheckboxList::make("job_scopes_grouped.{$descriptionId}")
                                                                        ->hiddenLabel()
                                                                        ->extraAttributes(['class' => 'job-scope-checkbox-list'])
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
                                                    ->action(function (array $data, Forms\Set $set, Forms\Get $get, $component) {
                                                        $groupedData = $data['job_scopes_grouped'] ?? [];
                                                        $rootSelectedIds = $data['job_scopes_root'] ?? [];

                                                        $parentCategoryId = $get('offering_category_id');
                                                        $parentCategory = \App\Models\Common\OfferingCategory::with(['children.offerings', 'offerings'])->find($parentCategoryId);
                                                        if (!$parentCategory) return;

                                                        $childCategories = $parentCategory->children()->defaultOrder()->get();
                                                        $currentChildren = $get('children') ?? [];
                                                        $currentItems = $get('items') ?? [];
                                                        $existingOfferingIds = array_column($currentItems, 'offering_id');
                                                        
                                                        // Get IDs of offerings that belong to the root category
                                                        $rootCategoryOfferingIds = $parentCategory->offerings->pluck('id')->map('strval')->toArray();
                                                        
                                                        $addedCount = 0;
                                                        $removedCount = 0;

                                                        // --- Handle Root Offerings (Removal and Addition) ---
                                                        // 1. Remove items that belong to this root category but are NO LONGER selected
                                                        $newItems = [];
                                                        foreach ($currentItems as $item) {
                                                            $offeringId = (string)($item['offering_id'] ?? '');
                                                            if (in_array($offeringId, $rootCategoryOfferingIds)) {
                                                                if (in_array($offeringId, $rootSelectedIds)) {
                                                                    $newItems[] = $item;
                                                                } else {
                                                                    $removedCount++;
                                                                }
                                                            } else {
                                                                // Preserve items not belonging to this category
                                                                $newItems[] = $item;
                                                            }
                                                        }
                                                        $currentItems = $newItems;
                                                        $existingOfferingIds = array_column($currentItems, 'offering_id');

                                                        // 2. Add newly selected root offerings
                                                        foreach ($rootSelectedIds as $offeringId) {
                                                            if (!in_array($offeringId, $existingOfferingIds)) {
                                                                $offering = $parentCategory->offerings->firstWhere('id', $offeringId);
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

                                                        // Sort root items
                                                        $parentOfferingSortOrders = $parentCategory->offerings->pluck('sort_order', 'id')->toArray();
                                                        usort($currentItems, function ($a, $b) use ($parentOfferingSortOrders) {
                                                            $orderA = $parentOfferingSortOrders[$a['offering_id']] ?? 0;
                                                            $orderB = $parentOfferingSortOrders[$b['offering_id']] ?? 0;
                                                            
                                                            if ($orderA === $orderB) {
                                                                return strcmp($a['description'] ?? '', $b['description'] ?? '');
                                                            }
                                                            
                                                            return $orderA <=> $orderB;
                                                        });
                                                        $set('items', $currentItems);
                                                        
                                                        // --- Handle Child Category Groups (Removal and Addition) ---
                                                        $newChildren = [];
                                                        $processedChildCategoryIds = [];

                                                        foreach ($childCategories as $childCategory) {
                                                            $categoryId = $childCategory->id;
                                                            $selectedIds = $groupedData[$categoryId] ?? [];
                                                            $processedChildCategoryIds[] = $categoryId;
                                                            
                                                            // Find existing child group
                                                            $existingChild = collect($currentChildren)->firstWhere('offering_category_id', $categoryId);
                                                            $items = $existingChild['items'] ?? [];
                                                            
                                                            // IDs of offerings belonging to THIS child category
                                                            $childCategoryOfferingIds = $childCategory->offerings->pluck('id')->map('strval')->toArray();

                                                            // 1. Remove items that belong to this child category but are NO LONGER selected
                                                            $newChildItems = [];
                                                            foreach ($items as $item) {
                                                                $offeringId = (string)($item['offering_id'] ?? '');
                                                                if (in_array($offeringId, $childCategoryOfferingIds)) {
                                                                    if (in_array($offeringId, $selectedIds)) {
                                                                        $newChildItems[] = $item;
                                                                    } else {
                                                                        $removedCount++;
                                                                    }
                                                                } else {
                                                                    $newChildItems[] = $item;
                                                                }
                                                            }
                                                            $items = $newChildItems;
                                                            $existingChildOfferingIds = array_column($items, 'offering_id');

                                                            // 2. Add newly selected offerings to this child group
                                                            foreach ($selectedIds as $offeringId) {
                                                                if (!in_array($offeringId, $existingChildOfferingIds)) {
                                                                    $offering = $childCategory->offerings->firstWhere('id', $offeringId);
                                                                    if ($offering) {
                                                                        $items[] = [
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

                                                            // If no items left and it's not a custom group, we don't need to keep/create it
                                                            if (empty($items)) {
                                                                continue;
                                                            }

                                                            // Update or build child group structure
                                                            if ($existingChild) {
                                                                $existingChild['items'] = $items;
                                                            } else {
                                                                $existingChild = [
                                                                    'id' => null,
                                                                    'offering_category_id' => $childCategory->id,
                                                                    'parent_id' => $get('id'),
                                                                    'name' => $childCategory->name,
                                                                    'order' => count($newChildren) + 1,
                                                                    'items' => $items,
                                                                ];
                                                            }

                                                            // Sort items in this child group
                                                            $allOfferingSortOrders = $childCategory->offerings->pluck('sort_order', 'id')->toArray();
                                                            usort($existingChild['items'], function ($a, $b) use ($allOfferingSortOrders) {
                                                                $orderA = $allOfferingSortOrders[$a['offering_id']] ?? 0;
                                                                $orderB = $allOfferingSortOrders[$b['offering_id']] ?? 0;
                                                                
                                                                if ($orderA === $orderB) {
                                                                    return strcmp($a['description'] ?? '', $b['description'] ?? '');
                                                                }
                                                                
                                                                return $orderA <=> $orderB;
                                                            });

                                                            $newChildren[] = $existingChild;
                                                        }

                                                        // Preserve other child groups that weren't managed by this selection (e.g. manually added sub-groups)
                                                        foreach ($currentChildren as $child) {
                                                            if (!in_array($child['offering_category_id'] ?? null, $processedChildCategoryIds)) {
                                                                $newChildren[] = $child;
                                                            }
                                                        }

                                                        $set('children', $newChildren);
                                                        
                                                        if ($addedCount > 0 || $removedCount > 0) {
                                                            $message = $addedCount . ' added';
                                                            if ($removedCount > 0) {
                                                                $message .= ', ' . $removedCount . ' removed';
                                                            }
                                                            Notification::make()
                                                                ->title('Job Scope items updated: ' . $message)
                                                                ->success()
                                                                ->send();
                                                        }
                                                    }),
                                            ]),
                                // Nested child groups
                                Forms\Components\Repeater::make('children')
                                    ->extraAttributes(['class' => 'item-group-sub'])
                                    ->relationship('children')
                                    ->saveRelationshipsUsing(null)
                                    ->dehydrated(true)
                                    ->orderColumn('order')
                                    ->label('Sub-Groups')
                                    ->hiddenLabel()
                                    ->collapsible()
                                    ->addActionLabel('Add sub-group')
                                    // ->visible(fn (Forms\Get $get) => filled($get('offering_category_id')))
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->schema([
                                        Forms\Components\Hidden::make('id'),
                                        Forms\Components\Hidden::make('offering_category_id'),
                                        Forms\Components\Hidden::make('parent_id'),
                                        Forms\Components\TextInput::make('name')
                                            ->label('Sub-Section Name')
                                            ->hidden(fn(Forms\Get $get) => filled($get('offering_category_id')) && ! config('erp.allow_edit_sub_group_header', false))
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
                                            ->addable(fn (Forms\Get $get) => !(filled($get('offering_category_id')) && config('erp.hide_add_item_for_sub_group', false)))
                                            ->headers(function (Forms\Get $get) use ($settings) {
                                                $discountMethod = DocumentDiscountMethod::parse($get('../../../../discount_method'));
                                                $hasDiscounts = $discountMethod->isPerLineItem();

                                                $headers = [
                                                    Header::make($settings->resolveColumnLabel('item_name', 'Items'))
                                                        ->width('50%'),
                                                    Header::make('Unit')
                                                        ->width('7%')
                                                        ->markAsRequired(false),
                                                    Header::make($settings->resolveColumnLabel('unit_name', 'Quantity'))
                                                        ->width('8%'),
                                                    Header::make($settings->resolveColumnLabel('price_name', 'Price'))
                                                        ->width('10%'),
                                                ];

                                                if (! config('erp.hide_tax_and_adjustment_fields', false)) {
                                                    if ($hasDiscounts) {
                                                        $headers[] = Header::make('Adjustments')->width('15%');
                                                    } else {
                                                        $headers[] = Header::make('Taxes')->width('15%');
                                                    }
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
                                                        ->required(fn (Forms\Get $get) => filled($get('offering_id')))
                                                        ->live()
                                                        ->inlineSuffix()
                                                        ->sellable()
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
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
                                                    ->numeric()
                                                    ->live(onBlur: true)
                                                    ->maxValue(9999999999.99)
                                                    ->default(1),
                                                Forms\Components\TextInput::make('unit_price')
                                                    ->hiddenLabel()
                                                    ->money(useAffix: false)
                                                    ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                    ->dehydrated(true)
                                                    ->live(onBlur: true)
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
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
                                                            $discountMethod = DocumentDiscountMethod::parse($get('../../../../../../discount_method'));

                                                            return $discountMethod->isPerDocument();
                                                        })
                                                        ->searchable(),
                                                ])->columnSpan(1)
                                                  ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false)),
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
                                                        $currencyCode = CurrencyAccessor::getDefaultCurrency();

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
                                                    ->button()
                                                    ->color('primary')
                                                    ->icon('heroicon-m-plus')
                                                    // ->visible(fn (Forms\Get $get) => filled($get('offering_category_id')))
                                                    ->fillForm(function (Forms\Get $get) {
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::with(['offerings'])->find($categoryId);

                                                        $existingItems = $get('items') ?? [];
                                                        $existingOfferingIds = array_column($existingItems, 'offering_id');

                                                        $preSelected = [];
                                                        if ($category) {
                                                            $preSelected = $category->offerings->pluck('id')
                                                                ->map('strval')
                                                                ->filter(fn($id) => in_array($id, $existingOfferingIds))
                                                                ->values()
                                                                ->toArray();
                                                        }

                                                        return [
                                                            'job_scopes' => $preSelected,
                                                        ];
                                                    })
                                                    ->form(function (Forms\Get $get) {
                                                        $categoryId = $get('offering_category_id');
                                                        $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);

                                                        $offerings = $category ? $category->offerings()
                                                            ->orderBy('sort_order')
                                                            ->orderBy('name')
                                                            ->get() : collect();

                                                        if ($offerings->isEmpty()) {
                                                            return [
                                                                Forms\Components\Placeholder::make('no_options')
                                                                    ->content('No Job Scopes available for this category.'),
                                                            ];
                                                        }

                                                        return [
                                                            Forms\Components\CheckboxList::make('job_scopes')
                                                                ->hiddenLabel()
                                                                ->extraAttributes(['class' => 'job-scope-checkbox-list'])
                                                                ->options($offerings->pluck('name', 'id'))
                                                                ->bulkToggleable()
                                                                ->searchable(),
                                                        ];
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
                                
                            ])
                            ->columnSpanFull(),
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
            ->columns([
                Tables\Columns\TextColumn::make('header')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subheader')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('lineItemGroups_count')
                    ->counts('lineItemGroups')
                    ->label('Groups'),
                Tables\Columns\TextColumn::make('lineItems_count')
                    ->counts('lineItems')
                    ->label('Items'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('createEstimate')
                    ->label('Use Template')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->action(function (EstimateTemplate $record) {
                        $replica = $record->replicate([
                            'is_template',
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
                        ]);
                        
                        $replica->is_template = false;
                        $replica->status = EstimateStatus::Draft;
                        $replica->estimate_number = Estimate::getNextDocumentNumber();
                        $replica->date = company_today();
                        $replica->currency_code = \App\Utilities\Currency\CurrencyAccessor::getDefaultCurrency();
                        // Expiration date logic
                        $settings = Auth::user()->currentCompany->defaultEstimate;
                        $replica->expiration_date = company_today()->addDays($settings->payment_terms->getDays());
                        
                        $replica->save();
                        
                        $record->replicateLineItems($replica);
                        
                        Notification::make()
                            ->title('Estimate created from template')
                            ->success()
                            ->send();
                            
                        return redirect(EstimateResource::getUrl('edit', ['record' => $replica]));
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListEstimateTemplates::route('/'),
            'create' => Pages\CreateEstimateTemplate::route('/create'),
            'edit' => Pages\EditEstimateTemplate::route('/{record}/edit'),
        ];
    }
}

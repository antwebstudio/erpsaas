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
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                CurrentCompanyScope::class,
            ]);

        $user = \Illuminate\Support\Facades\Auth::user();

        // Users with only view_mine should only see their own variation orders
        if ($user && ! $user->can('view_any_sales::variation::order') && $user->can('view_mine_sales::variation::order')) {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    protected static ?string $modelLabel = 'Variation Order';

    protected static ?string $pluralModelLabel = 'Variation Orders';

    protected static ?string $slug = 'variation-orders';

    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        if (config('erp.hide_estimate_in_navigation', false)) {
            return false;
        }

        return static::canViewAny();
    }
    
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
                                    ->forceEnableRelationship()
                                    ->saveRelationshipsUsing(null)
                                    ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false)),
                            ])->grow(true),
                        ])->from('md'),
                    ]),

                Forms\Components\Section::make('Line Items')
                    ->schema([
                        Forms\Components\Repeater::make('lineItemGroups')
                            ->label('Item Groups')
                            ->relationship('lineItemGroups', function ($query) {
                                $query->whereNull('parent_id')->with(['children' => function ($query) {
                                    $query->orderBy('order');
                                }, 'items' => function ($query) {
                                    $query->orderBy('line_number');
                                }])->orderBy('order');
                            })
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(true)
                            ->orderColumn('order')
                            ->defaultItems(0)
                            ->extraAttributes(['class' => 'item-group-darker'])
                            ->extraItemActions([
                                Forms\Components\Actions\Action::make('add_job_scope')
                                    ->label('Select Job Scope')
                                    ->button()
                                    ->color('primary')
                                    ->icon('heroicon-m-plus')
                                    ->visible(function (Forms\Components\Repeater $component, array $arguments) {
                                        if (!isset($arguments['item'])) return false;
                                        $itemState = $component->getState()[$arguments['item']] ?? [];
                                        return filled($itemState['offering_category_id'] ?? null);
                                    })
                                    ->fillForm(function (Forms\Components\Repeater $component, array $arguments) {
                                        $itemState = $component->getState()[$arguments['item']] ?? [];
                                        $categoryId = $itemState['offering_category_id'] ?? null;
                                        $category = \App\Models\Common\OfferingCategory::with(['children.offerings', 'offerings'])->find($categoryId);

                                        $existingItems = $itemState['items'] ?? [];
                                        $existingOfferingIds = array_column($existingItems, 'offering_id');

                                        $existingChildren = $itemState['children'] ?? [];
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
                                    ->form(function (Forms\Components\Repeater $component, array $arguments) {
                                        $itemState = $component->getState()[$arguments['item']] ?? [];
                                        $categoryId = $itemState['offering_category_id'] ?? null;
                                        $category = \App\Models\Common\OfferingCategory::with('offerings')->find($categoryId);

                                        $jobScopeDescriptions = $category ? $category->children()->with('offerings')->get() : collect();
                                        $rootOfferings = $category ? $category->offerings()
                                            ->orderBy('sort_order')
                                            ->get()
                                            ->map(fn($o) => ['id' => (string)$o->id, 'name' => $o->name, 'sort_order' => $o->sort_order])
                                            ->values()
                                            ->toArray() : [];

                                        $schema = [];

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
                                                ->extraAttributes(['class' => 'job-scope-section'])
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
                                                    return collect($rootOfferings)->contains(function ($item) use ($term) {
                                                        return \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term));
                                                    });
                                                });
                                        }

                                        foreach ($jobScopeDescriptions as $description) {
                                            $descriptionName = $description->name;
                                            $descriptionId = $description->id;
                                            $allOfferings = $description->offerings()
                                                ->orderBy('sort_order')
                                                ->get()
                                                ->map(fn($o) => ['id' => (string)$o->id, 'name' => $o->name, 'sort_order' => $o->sort_order])
                                                ->values()
                                                ->toArray();

                                            if (empty($allOfferings)) {
                                                continue;
                                            }

                                            $schema[] = Forms\Components\Section::make($descriptionName)
                                                ->extraAttributes(['class' => 'job-scope-section'])
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
                                                    return collect($allOfferings)->contains(function ($item) use ($term) {
                                                        return \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term));
                                                    });
                                                });
                                        }

                                        return $schema;
                                    })
                                    ->action(function (array $data, Forms\Set $set, array $arguments, Forms\Components\Repeater $component) {
                                        $itemState = $component->getState()[$arguments['item']] ?? [];
                                        $groupedData = $data['job_scopes_grouped'] ?? [];
                                        $rootSelectedIds = $data['job_scopes_root'] ?? [];

                                        $parentCategoryId = $itemState['offering_category_id'] ?? null;
                                        $parentCategory = \App\Models\Common\OfferingCategory::with(['children.offerings', 'offerings'])->find($parentCategoryId);
                                        if (!$parentCategory) return;

                                        $childCategories = $parentCategory->children()->defaultOrder()->get();
                                        $currentChildren = $itemState['children'] ?? [];
                                        $currentItems = $itemState['items'] ?? [];

                                        $rootCategoryOfferingIds = $parentCategory->offerings->pluck('id')->map('strval')->toArray();

                                        $addedCount = 0;
                                        $removedCount = 0;

                                        // --- Handle Root Offerings (Removal and Addition) ---
                                        $newItems = [];
                                        foreach ($currentItems as $key => $item) {
                                            $offeringId = (string)($item['offering_id'] ?? '');
                                            if (in_array($offeringId, $rootCategoryOfferingIds)) {
                                                if (in_array($offeringId, $rootSelectedIds)) {
                                                    $newItems[$key] = $item;
                                                } else {
                                                    $removedCount++;
                                                }
                                            } else {
                                                $newItems[$key] = $item;
                                            }
                                        }
                                        $currentItems = $newItems;

                                        // Add newly selected root offerings
                                        foreach ($rootSelectedIds as $offeringId) {
                                            // Check if it already exists
                                            $exists = false;
                                            foreach ($currentItems as $item) {
                                                if ((string)($item['offering_id'] ?? '') === (string)$offeringId) {
                                                    $exists = true;
                                                    break;
                                                }
                                            }

                                            if (!$exists) {
                                                $offering = $parentCategory->offerings->firstWhere('id', $offeringId);
                                                if ($offering) {
                                                    $newKey = (string) \Illuminate\Support\Str::uuid();
                                                    $currentItems[$newKey] = [
                                                        'id' => null,
                                                        'offering_id' => $offering->id,
                                                        'description' => $offering->name,
                                                        'is_locked' => 1,
                                                        'unit' => $offering->unit,
                                                        'quantity' => 1,
                                                        'unit_price' => \App\Utilities\Currency\CurrencyConverter::convertCentsToFormatSimple($offering->price, 'USD'),
                                                        'salesDiscounts' => [],
                                                        'salesTaxes' => [],
                                                    ];
                                                    $addedCount++;
                                                }
                                            }
                                        }

                                        // Sort root items
                                        $parentOfferingSortOrders = $parentCategory->offerings->pluck('sort_order', 'id')->toArray();
                                        uasort($currentItems, function ($a, $b) use ($parentOfferingSortOrders) {
                                            $orderA = $parentOfferingSortOrders[$a['offering_id']] ?? 0;
                                            $orderB = $parentOfferingSortOrders[$b['offering_id']] ?? 0;
                                            if ($orderA === $orderB) {
                                                return strcmp($a['description'] ?? '', $b['description'] ?? '');
                                            }
                                            return $orderA <=> $orderB;
                                        });
                                        $allState = $component->getState();
                                        $allState[$arguments['item']]['items'] = $currentItems;
                                        $component->state($allState);

                                        // --- Handle Child Category Groups (Removal and Addition) ---
                                        $newChildren = [];
                                        $processedChildCategoryIds = [];

                                        foreach ($childCategories as $childCategory) {
                                            $selectedIds = $groupedData[$childCategory->id] ?? [];
                                            $childCategoryOfferingIds = $childCategory->offerings->pluck('id')->map('strval')->toArray();
                                            $processedChildCategoryIds[] = $childCategory->id;

                                            // Find existing child group for this category
                                            $existingChildKey = null;
                                            $existingChild = null;
                                            foreach ($currentChildren as $key => $child) {
                                                if (($child['offering_category_id'] ?? null) == $childCategory->id) {
                                                    $existingChildKey = $key;
                                                    $existingChild = $child;
                                                    break;
                                                }
                                            }

                                            $items = $existingChild['items'] ?? [];

                                            // 1. Remove items that belong to this child category but are NO LONGER selected
                                            $newChildItems = [];
                                            foreach ($items as $itemKey => $item) {
                                                $offeringId = (string)($item['offering_id'] ?? '');
                                                if (in_array($offeringId, $childCategoryOfferingIds)) {
                                                    if (in_array($offeringId, $selectedIds)) {
                                                        $newChildItems[$itemKey] = $item;
                                                    } else {
                                                        $removedCount++;
                                                    }
                                                } else {
                                                    $newChildItems[$itemKey] = $item;
                                                }
                                            }
                                            $items = $newChildItems;

                                            // 2. Add newly selected offerings to this child group
                                            foreach ($selectedIds as $selectedId) {
                                                if (in_array((string)$selectedId, $childCategoryOfferingIds)) {
                                                    $exists = false;
                                                    foreach ($items as $item) {
                                                        if ((string)($item['offering_id'] ?? '') === (string)$selectedId) {
                                                            $exists = true;
                                                            break;
                                                        }
                                                    }

                                                    if (!$exists) {
                                                        $offering = $childCategory->offerings->firstWhere('id', $selectedId);
                                                        if ($offering) {
                                                            $newItemKey = (string) \Illuminate\Support\Str::uuid();
                                                            $items[$newItemKey] = [
                                                                'id' => null,
                                                                'offering_id' => $offering->id,
                                                                'description' => $offering->name,
                                                                'is_locked' => 1,
                                                                'unit' => $offering->unit,
                                                                'quantity' => 1,
                                                                'unit_price' => \App\Utilities\Currency\CurrencyConverter::convertCentsToFormatSimple($offering->price, 'USD'),
                                                                'salesDiscounts' => [],
                                                                'salesTaxes' => [],
                                                            ];
                                                            $addedCount++;
                                                        }
                                                    }
                                                }
                                            }

                                            // If no items left and it's not a custom group, we don't need to keep/create it
                                            if (empty($items)) {
                                                continue;
                                            }

                                            // Sort items in this child group
                                            $childOfferingSortOrders = $childCategory->offerings->pluck('sort_order', 'id')->toArray();
                                            uasort($items, function ($a, $b) use ($childOfferingSortOrders) {
                                                $orderA = $childOfferingSortOrders[$a['offering_id']] ?? 0;
                                                $orderB = $childOfferingSortOrders[$b['offering_id']] ?? 0;
                                                if ($orderA === $orderB) {
                                                    return strcmp($a['description'] ?? '', $b['description'] ?? '');
                                                }
                                                return $orderA <=> $orderB;
                                            });

                                            // Update or build child group structure
                                            if ($existingChildKey !== null) {
                                                $existingChild['items'] = $items;
                                                $newChildren[$existingChildKey] = $existingChild;
                                            } else {
                                                $newChildKey = (string) \Illuminate\Support\Str::uuid();
                                                $newChildren[$newChildKey] = [
                                                    'id' => null,
                                                    'offering_category_id' => $childCategory->id,
                                                    'parent_id' => $itemState['id'] ?? null,
                                                    'name' => $childCategory->name,
                                                    'order' => count($newChildren) + 1,
                                                    'items' => $items,
                                                ];
                                            }
                                        }

                                        // Preserve other child groups that weren't managed by this selection
                                        foreach ($currentChildren as $key => $child) {
                                            if (!in_array($child['offering_category_id'] ?? null, $processedChildCategoryIds)) {
                                                $newChildren[$key] = $child;
                                            }
                                        }

                                        $allState = $component->getState();
                                        $allState[$arguments['item']]['children'] = $newChildren;
                                        $component->state($allState);

                                        $message = [];
                                        if ($addedCount > 0) $message[] = "{$addedCount} item(s) added";
                                        if ($removedCount > 0) $message[] = "{$removedCount} item(s) removed";

                                        \Filament\Notifications\Notification::make()
                                            ->title('Job scope updated')
                                            ->body(implode(', ', $message) ?: 'No changes made.')
                                            ->success()
                                            ->send();
                                    }),
                            ])
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

                                // Nested child groups (Sub-Groups)
                                Forms\Components\Repeater::make('children')
                                    ->extraAttributes(['class' => 'item-group-sub'])
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
                                                    Header::make($settings?->resolveColumnLabel('item_name', 'Items') ?? 'Items')
                                                        ->width('45%'),
                                                    Header::make($settings?->resolveColumnLabel('unit_name', 'Quantity') ?? 'Quantity')
                                                        ->width('8%'),
                                                    Header::make('Unit')
                                                        ->width('12%')
                                                        ->markAsRequired(false),
                                                    Header::make($settings?->resolveColumnLabel('price_name', 'Price') ?? 'Price')
                                                        ->width('10%'),
                                                ];

                                                if (config('erp.show_variation_order_kiv', false)) {
                                                    $headers[] = Header::make('KIV')->width('5%');
                                                }

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
                                                    Forms\Components\Hidden::make('offering_id')
                                                        ->default(0),
                                                    Forms\Components\TextInput::make('description')
                                                        ->placeholder('Enter item description')
                                                        ->dehydrated(true)
                                                        ->hiddenLabel(),
                                                ])->columnSpan(1),
                                                Forms\Components\TextInput::make('quantity')
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')) && $get('offering_id') != '0')
                                                    ->numeric()
                                                    ->live(onBlur: true)
                                                    ->maxValue(9999999999.99)
                                                    ->default(1),
                                                Forms\Components\TextInput::make('unit')
                                                    ->placeholder('Unit')
                                                    ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                    ->dehydrated(true)
                                                    ->hiddenLabel(),
                                                Forms\Components\TextInput::make('unit_price')
                                                    ->hiddenLabel()
                                                    ->money(useAffix: false)
                                                    ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                                    ->dehydrated(true)
                                                    ->live(onBlur: true)
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')) && $get('offering_id') != '0')
                                                    ->default(0),
                                                Forms\Components\Checkbox::make('kiv')
                                                    ->label('KIV')
                                                    ->dehydrated(true)
                                                    ->default(false)
                                                    ->hidden(fn () => ! config('erp.show_variation_order_kiv', false)),
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
                                    ->addable(fn (Forms\Get $get) => !(filled($get('offering_category_id')) && config('erp.hide_add_item_for_group', false)))
                                    ->headers(function (Forms\Get $get) use ($settings) {
                                        $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                        $hasDiscounts = $discountMethod->isPerLineItem();

                                        $headers = [
                                            Header::make($settings?->resolveColumnLabel('item_name', 'Items') ?? 'Items')
                                                ->width('45%'),
                                            Header::make($settings?->resolveColumnLabel('unit_name', 'Quantity') ?? 'Quantity')
                                                ->width('8%'),
                                            Header::make('Unit')
                                                ->width('12%')
                                                ->markAsRequired(false),
                                            Header::make($settings?->resolveColumnLabel('price_name', 'Price') ?? 'Price')
                                                ->width('10%'),
                                        ];

                                        if (config('erp.show_variation_order_kiv', false)) {
                                            $headers[] = Header::make('KIV')->width('5%');
                                        }

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
                                            Forms\Components\Hidden::make('offering_id')
                                                ->default(0),
                                            Forms\Components\TextInput::make('description')
                                                ->placeholder('Enter item description')
                                                ->dehydrated(true)
                                                ->hiddenLabel(),
                                        ])->columnSpan(1),
                                        Forms\Components\TextInput::make('quantity')
                                            ->required(fn (Forms\Get $get) => filled($get('offering_id')) && $get('offering_id') != '0')
                                            ->numeric()
                                            ->live(onBlur: true)
                                            ->maxValue(9999999999.99)
                                            ->default(1),
                                        Forms\Components\TextInput::make('unit')
                                            ->placeholder('Unit')
                                            ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                            ->dehydrated(true)
                                            ->hiddenLabel(),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->hiddenLabel()
                                            ->money(useAffix: false)
                                            ->readonly(fn (Forms\Get $get) => $get('is_locked') >= 2)
                                            ->dehydrated(true)
                                            ->live(onBlur: true)
                                            ->required(fn (Forms\Get $get) => filled($get('offering_id')) && $get('offering_id') != '0')
                                            ->default(0),
                                        Forms\Components\Checkbox::make('kiv')
                                            ->label('KIV')
                                            ->dehydrated(true)
                                            ->default(false),
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
                                    ]),
                            ]),
                        DocumentTotals::make()
                            ->type(DocumentType::VariationOrder),
                        Forms\Components\Select::make('template_company_id')
                            ->label('Issue Company')
                            ->relationship(
                                name: 'templateCompany',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('id', '!=', config('erp.erp_system_company_id')),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $company = \App\Models\Company::with(['profile' => fn($query) => $query->withoutGlobalScopes()])->find($state);
                                $defaultTaxId = $company?->profile?->default_sales_tax_id;

                                if ($defaultTaxId) {
                                    $set('salesTaxes', [$defaultTaxId]);
                                } else {
                                    $set('salesTaxes', []);
                                }
                            }),
                        Forms\Components\Textarea::make('terms')
                            ->default($settings?->terms)
                            ->columnSpanFull()
                            ->hidden(fn () => config('erp.hide_document_terms', false)),
                        Forms\Components\Textarea::make('notes')
                            ->default($settings?->notes)
                            ->columnSpanFull()
                            ->hidden(fn () => config('erp.hide_document_terms', false) || config('erp.hide_document_notes', false)),
                    ]),
                DocumentFooterSection::make('Variation Order Footer')
                    ->defaultFooter($settings?->footer)
                    ->hidden(fn () => config('erp.hide_document_footer', false)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->isNotTemplate())
            ->defaultSort('date', 'desc')
            ->recordAction(Tables\Actions\ViewAction::class)
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Issuing Company')
                    ->getStateUsing(fn (VariationOrder $record) => $record->templateCompany?->name ?? $record->company->name)
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

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
                Tables\Columns\TextColumn::make('last_sent_at')
                    ->label('Last Sent At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
                        Tables\Actions\EditAction::make()
                            ->url(static fn (VariationOrder $record) => Pages\EditVariationOrder::getUrl(['record' => $record])),
                        Tables\Actions\ViewAction::make()
                            ->url(static fn (VariationOrder $record) => Pages\ViewVariationOrder::getUrl(['record' => $record])),
                        VariationOrder::getPreviewAction(Tables\Actions\Action::class),
                        VariationOrder::getReplicateAction(Tables\Actions\ReplicateAction::class),
                        VariationOrder::getApproveDraftAction(Tables\Actions\Action::class),
                        VariationOrder::getSendEmailAction(Tables\Actions\Action::class),
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

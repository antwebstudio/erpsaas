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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class EstimateResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = Estimate::class;

    public static function shouldRegisterNavigation(): bool
    {
        if (config('erp.hide_estimate_in_navigation', false)) {
            return false;
        }

        return static::canViewAny();
    }


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
                                    ->label('Estimate Number')
                                    ->default(static fn () => Estimate::getNextDocumentNumber()),
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
                                        ->hidden(fn () => ! config('erp.show_expiry_date', true))
										->nullable(fn () => ! config('erp.show_expiry_date', true))
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
                                    ->hidden(fn () => ! config('erp.show_expiry_date', true))
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
                                        if ($term) {
                                            $expected = Carbon::parse($date)->addDays($term->getDays());

                                            if (! Carbon::parse($state)->isSameDay($expected)) {
                                                $set('payment_terms', 'custom');
                                            }
                                        }
                                    }),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount method')
                                    ->options(DocumentDiscountMethod::class)
                                    ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false))
                                    ->softRequired()
                                    ->default($settings->discount_method ?? \App\Enums\Accounting\DocumentDiscountMethod::PerDocument)
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
                                    ->saveRelationshipsUsing(null)
                                    ->hidden(fn () => config('erp.hide_tax_and_adjustment_fields', false)),
                            ])->grow(true),
                        ])->from('md'),
                        Forms\Components\Repeater::make('lineItemGroups')
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
                                        
                                        // Gather all existing offering IDs in this group (main items + sub-group items)
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
                                        
                                        // Get all children categories (Job Scope Descriptions)
                                        // And their offerings (Job Scope Options)
                                        $jobScopeDescriptions = $category ? $category->children()->with('offerings')->get() : collect();
                                        $rootOfferings = $category ? $category->offerings()
                                            ->orderBy('sort_order')
                                            
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
                                            // We need to pass the offerings data (id => name) or the collection to the closure
                                            // But the closure needs to filter it.
                                            // To avoid serializing large objects, let's pass a simple array of [id, name]
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
                                                    // Check if any offering matches
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
                                        $allState = $component->getState();
                                        $allState[$arguments['item']]['items'] = $currentItems;
                                        $component->state($allState);
                                        
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
                                                    'parent_id' => $itemState['id'] ?? null,
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

                                        $allState = $component->getState();
                                        $allState[$arguments['item']]['children'] = $newChildren;
                                        $component->state($allState);
                                        
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
                            ])
                            ->relationship('lineItemGroups', function (Builder $query) {
                                return $query->whereNull('parent_id')->with([
                                    'items.offering.salesTaxes', 
                                    'items.offering.salesDiscounts', 
                                    'items.sellableOffering.salesTaxes', 
                                    'items.sellableOffering.salesDiscounts', 
                                    'items.salesTaxes', 
                                    'items.salesDiscounts',
                                    'children.items.offering.salesTaxes',
                                    'children.items.offering.salesDiscounts',
                                    'children.items.sellableOffering.salesTaxes',
                                    'children.items.sellableOffering.salesDiscounts',
                                    'children.items.salesTaxes',
                                    'children.items.salesDiscounts',
                                ]);
                            })
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
                                    ->dehydrated(true)
                                    ->dehydratedWhenHidden()
                                    ->placeholder('e.g. Materials, Labor')
                                    ->columnSpanFull(),
                                
                                // Original items repeater for parent groups without children (backward compatibility)
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
                                                    CreateOfferingSelect::make('offering_id', true)
                                                        ->label('Item')
                                                        ->hiddenLabel()
                                                        ->placeholder('Select item')
                                                        ->default('0')
                                                        ->required(fn (Forms\Get $get) => $get('offering_id') != '0')
                                                        ->live()
                                                        ->inlineSuffix()
                                                        ->sellable()
                                                        ->options(function (Forms\Get $get) {
                                                            $categoryId = $get('../../offering_category_id') ?: $get('../../../../offering_category_id');
                                                            if (! $categoryId) {
                                                                return \App\Models\Common\Offering::where('sellable', true)->pluck('name', 'id')->toArray();
                                                            }
                                                            $category = \App\Models\Common\OfferingCategory::find($categoryId);
                                                            if (! $category) {
                                                                return [];
                                                            }
                                                            // Collect this category + all descendant category IDs
                                                            $categoryIds = \App\Models\Common\OfferingCategory::where('_lft', '>=', $category->_lft)
                                                                ->where('_rgt', '<=', $category->_rgt)
                                                                ->pluck('id')
                                                                ->toArray();
                                                            return \App\Models\Common\Offering::whereHas('categories', fn ($q) => $q->whereIn('offering_categories.id', $categoryIds))
                                                                ->where('sellable', true)
                                                                ->pluck('name', 'id')
                                                                ->toArray();
                                                        })
                                                        ->searchable()
                                                        ->hidden(fn (Forms\Get $get) => $get('is_locked') >= 1 || $get('offering_id') == '0')
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
                                                        ->preload()
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
                                                        $companyId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->current_company_id ?? 1;
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
                                            ]),
                                        // Nested child groups
                                Forms\Components\Repeater::make('children')
                                    ->extraAttributes(['class' => 'item-group-darker'])
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
                                                    CreateOfferingSelect::make('offering_id', true)
                                                        ->label('Item')
                                                        ->hiddenLabel()
                                                        ->placeholder('Select item')
                                                        ->default('0')
                                                        ->required(fn (Forms\Get $get) => $get('offering_id') != '0')
                                                        ->live()
                                                        ->inlineSuffix()
                                                        ->sellable()
                                                        ->options(function (Forms\Get $get) {
                                                            $categoryId = $get('../../offering_category_id') ?: $get('../../../../offering_category_id');
                                                            if (! $categoryId) {
                                                                return \App\Models\Common\Offering::where('sellable', true)->pluck('name', 'id')->toArray();
                                                            }
                                                            $category = \App\Models\Common\OfferingCategory::find($categoryId);
                                                            if (! $category) {
                                                                return [];
                                                            }
                                                            // Collect this category + all descendant category IDs
                                                            $categoryIds = \App\Models\Common\OfferingCategory::where('_lft', '>=', $category->_lft)
                                                                ->where('_rgt', '<=', $category->_rgt)
                                                                ->pluck('id')
                                                                ->toArray();
                                                            return \App\Models\Common\Offering::whereHas('categories', fn ($q) => $q->whereIn('offering_categories.id', $categoryIds))
                                                                ->where('sellable', true)
                                                                ->pluck('name', 'id')
                                                                ->toArray();
                                                        })
                                                        ->searchable()
                                                        ->hidden(fn (Forms\Get $get) => $get('is_locked') >= 1 || $get('offering_id') == '0')
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
                                                        // ->preload()
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
                                                        $companyId = \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()?->current_company_id ?? 1;
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
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                                
                            ]),
                        DocumentTotals::make()
                            ->type(DocumentType::Estimate),
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

                                $company = \App\Models\Company::with(['profile' => fn($query) => $query->withoutGlobalScopes()])->find($state);
                                $defaultTaxId = $company?->profile?->default_sales_tax_id;

                                if ($defaultTaxId) {
                                    $set('salesTaxes', [$defaultTaxId]);
                                } else {
                                    $set('salesTaxes', []);
                                }
                            }),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->isNotTemplate()->where('status', '!=', EstimateStatus::Accepted))
            ->defaultSort('date', 'desc')
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Issuing Company')
                    ->getStateUsing(fn (Estimate $record) => $record->templateCompany?->name ?? $record->company->name)
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimate_number')
                    ->label('Estimate Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiration_date')
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
                    ->indicatorLabel('Expiration date')
                    ->hidden(fn () => ! config('erp.show_expiry_date', true)),

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
                        Estimate::getPreviewAction(Tables\Actions\Action::class),
                        Estimate::getReplicateAction(Tables\Actions\ReplicateAction::class),
                        Estimate::getApproveDraftAction(Tables\Actions\Action::class),
                        Estimate::getSendEmailAction(Tables\Actions\Action::class),
                        Estimate::getMarkAsSentAction(Tables\Actions\Action::class),
                        Estimate::getMarkAsAcceptedAction(Tables\Actions\Action::class),
                        Estimate::getMarkAsDeclinedAction(Tables\Actions\Action::class),
                        Estimate::getConvertToContractAction(Tables\Actions\Action::class),
                        Estimate::getConvertToInvoiceAction(Tables\Actions\Action::class),
                        Tables\Actions\Action::make('saveAsTemplate')
                            ->label('Save as Template')
                            ->icon('heroicon-o-document-duplicate')
                            ->action(function (Estimate $record) {
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

                                $replica->is_template = true;
                                $replica->header = $replica->header ?? $record->estimate_number . ' Template';
                                $replica->status = EstimateStatus::Draft;
                                $replica->save();

                                $record->replicateLineItems($replica);

                                Notification::make()
                                    ->title('Estimate saved as template')
                                    ->success()
                                    ->send();
                            }),
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

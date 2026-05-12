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
        if (config('erp.hide_estimate_template_in_navigation', false)) {
            return false;
        }

        return static::canViewAny();
    }


    protected static ?string $slug = 'sales/estimate-templates';

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
                                            $childOfferingIds = array_column($child['items'] ?? [], 'offering_id');
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
                                            ->orderBy('name')
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
                                                                $filtered = $filtered->filter(fn($item) => \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term)));
                                                            }
                                                            return $filtered->pluck('name', 'id')->toArray();
                                                        }),
                                                ])
                                                ->collapsible()
                                                ->compact()
                                                ->visible(function (Forms\Get $get) use ($rootOfferings) {
                                                    $term = $get('search_job_scopes');
                                                    if (blank($term)) return true;
                                                    return collect($rootOfferings)->contains(fn($item) => \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term)));
                                                });
                                        }

                                        foreach ($jobScopeDescriptions as $description) {
                                            $descriptionName = $description->name;
                                            $descriptionId = $description->id;
                                            $allOfferings = $description->offerings()
                                                ->orderBy('sort_order')
                                                ->orderBy('name')
                                                ->get()
                                                ->map(fn($o) => ['id' => (string)$o->id, 'name' => $o->name, 'sort_order' => $o->sort_order])
                                                ->values()
                                                ->toArray();

                                            if (empty($allOfferings)) continue;

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
                                                                $filtered = $filtered->filter(fn($item) => \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term)));
                                                            }
                                                            return $filtered->pluck('name', 'id')->toArray();
                                                        }),
                                                ])
                                                ->collapsible()
                                                ->compact()
                                                ->visible(function (Forms\Get $get) use ($allOfferings) {
                                                    $term = $get('search_job_scopes');
                                                    if (blank($term)) return true;
                                                    return collect($allOfferings)->contains(fn($item) => \Illuminate\Support\Str::contains(strtolower($item['name']), strtolower($term)));
                                                });
                                        }

                                        return $schema;
                                    })
                                    ->action(function (array $data, array $arguments, Forms\Components\Repeater $component) {
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

                                        // Handle root offerings
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

                                        foreach ($rootSelectedIds as $selectedId) {
                                            $exists = collect($currentItems)->contains(fn($item) => (string)($item['offering_id'] ?? '') === (string)$selectedId);
                                            if (!$exists) {
                                                $offering = \App\Models\Common\Offering::find($selectedId);
                                                if ($offering) {
                                                    $currentItems[(string) \Illuminate\Support\Str::uuid()] = [
                                                        'id' => null,
                                                        'offering_id' => $offering->id,
                                                        'description' => $offering->name,
                                                        'quantity' => 1,
                                                        'unit_price' => CurrencyConverter::convertCentsToFormatSimple($offering->price, 'USD'),
                                                        'unit' => $offering->unit,
                                                        'is_locked' => 1,
                                                        'salesDiscounts' => [],
                                                        'salesTaxes' => [],
                                                    ];
                                                    $addedCount++;
                                                }
                                            }
                                        }

                                        $parentOfferingSortOrders = $parentCategory->offerings->pluck('sort_order', 'id')->toArray();
                                        uasort($currentItems, function ($a, $b) use ($parentOfferingSortOrders) {
                                            $orderA = $parentOfferingSortOrders[$a['offering_id']] ?? 0;
                                            $orderB = $parentOfferingSortOrders[$b['offering_id']] ?? 0;
                                            return $orderA !== $orderB ? $orderA <=> $orderB : strcmp($a['description'] ?? '', $b['description'] ?? '');
                                        });

                                        $allState = $component->getState();
                                        $allState[$arguments['item']]['items'] = $currentItems;
                                        $component->state($allState);

                                        // Handle child category groups
                                        $newChildren = [];
                                        $processedChildCategoryIds = [];

                                        foreach ($childCategories as $childCategory) {
                                            $selectedIds = $groupedData[$childCategory->id] ?? [];
                                            $childCategoryOfferingIds = $childCategory->offerings->pluck('id')->map('strval')->toArray();
                                            $processedChildCategoryIds[] = $childCategory->id;

                                            $existingChildKey = null;
                                            $existingChild = null;
                                            foreach ($currentChildren as $key => $child) {
                                                if (($child['offering_category_id'] ?? null) === $childCategory->id) {
                                                    $existingChildKey = $key;
                                                    $existingChild = $child;
                                                    break;
                                                }
                                            }

                                            $items = $existingChild['items'] ?? [];

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

                                            foreach ($selectedIds as $selectedId) {
                                                $exists = collect($items)->contains(fn($item) => (string)($item['offering_id'] ?? '') === (string)$selectedId);
                                                if (!$exists) {
                                                    $offering = \App\Models\Common\Offering::find($selectedId);
                                                    if ($offering) {
                                                        $items[(string) \Illuminate\Support\Str::uuid()] = [
                                                            'id' => null,
                                                            'offering_id' => $offering->id,
                                                            'description' => $offering->name,
                                                            'quantity' => 1,
                                                            'unit_price' => CurrencyConverter::convertCentsToFormatSimple($offering->price, 'USD'),
                                                            'unit' => $offering->unit,
                                                            'is_locked' => 1,
                                                            'salesDiscounts' => [],
                                                            'salesTaxes' => [],
                                                        ];
                                                        $addedCount++;
                                                    }
                                                }
                                            }

                                            if (empty($items)) continue;

                                            $allOfferingSortOrders = $childCategory->offerings->pluck('sort_order', 'id')->toArray();
                                            uasort($items, function ($a, $b) use ($allOfferingSortOrders) {
                                                $orderA = $allOfferingSortOrders[$a['offering_id']] ?? 0;
                                                $orderB = $allOfferingSortOrders[$b['offering_id']] ?? 0;
                                                return $orderA !== $orderB ? $orderA <=> $orderB : strcmp($a['description'] ?? '', $b['description'] ?? '');
                                            });

                                            if ($existingChildKey !== null) {
                                                $existingChild['items'] = $items;
                                                $newChildren[$existingChildKey] = $existingChild;
                                            } else {
                                                $newChildren[(string) \Illuminate\Support\Str::uuid()] = [
                                                    'id' => null,
                                                    'offering_category_id' => $childCategory->id,
                                                    'parent_id' => $itemState['id'] ?? null,
                                                    'name' => $childCategory->name,
                                                    'order' => count($newChildren) + 1,
                                                    'items' => $items,
                                                ];
                                            }
                                        }

                                        foreach ($currentChildren as $key => $child) {
                                            if (!in_array($child['offering_category_id'] ?? null, $processedChildCategoryIds)) {
                                                $newChildren[$key] = $child;
                                            }
                                        }

                                        $allState = $component->getState();
                                        $allState[$arguments['item']]['children'] = $newChildren;
                                        $component->state($allState);

                                        if ($addedCount > 0 || $removedCount > 0) {
                                            $message = $addedCount . ' added';
                                            if ($removedCount > 0) $message .= ', ' . $removedCount . ' removed';
                                            Notification::make()
                                                ->title('Job Scope items updated: ' . $message)
                                                ->success()
                                                ->send();
                                        }
                                    }),
                            ])
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
                                                        ->width('45%'),
                                                    Header::make($settings->resolveColumnLabel('unit_name', 'Quantity'))
                                                        ->width('8%'),
                                                    Header::make('Unit')
                                                        ->width('12%')
                                                        ->markAsRequired(false),
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
                                                Forms\Components\TextInput::make('quantity')
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
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
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
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
                                                        ->width('45%'),
                                                    Header::make($settings->resolveColumnLabel('unit_name', 'Quantity'))
                                                        ->width('8%'),
                                                    Header::make('Unit')
                                                        ->width('12%')
                                                        ->markAsRequired(false),
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
                                                Forms\Components\TextInput::make('quantity')
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
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
                                                    ->required(fn (Forms\Get $get) => filled($get('offering_id')))
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

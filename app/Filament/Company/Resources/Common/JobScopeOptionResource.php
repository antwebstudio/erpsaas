<?php

namespace App\Filament\Company\Resources\Common;

use App\Enums\Common\OfferingType;
use App\Filament\Company\Resources\Common\JobScopeOptionResource\Pages;
use App\Models\Common\JobScopeOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JobScopeOptionResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = JobScopeOption::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Job Scope Options';

    protected static ?string $modelLabel = 'Job Scope Option';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\Hidden::make('type')
                            ->default(OfferingType::Service),
                        Forms\Components\Hidden::make('sellable')
                            ->default(true),
                        Forms\Components\Hidden::make('purchasable')
                            ->default(false),

                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->required()
                            ->money(),
                        Forms\Components\TextInput::make('unit')
                            ->label('Unit of Measurement')
                            ->placeholder('e.g. Hour, Job')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->rows(3),
                        Forms\Components\Select::make('categories')
                            ->label('Job Scopes')
                            ->relationship('categories', 'name', fn (Builder $query) => $query->whereNotNull('parent_id'))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(function () {
                                return \App\Models\Common\OfferingCategory::whereNotNull('parent_id')
                                    ->with(['parent'])
                                    ->get()
                                    ->groupBy(fn ($category) => $category->parent?->name ?? 'Uncategorized')
                                    ->map(fn ($items) => $items->pluck('name', 'id'))
                                    ->toArray();
                            })
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Job Scopes')
                    ->badge(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\Filter::make('category_drilldown')
                    ->form([
                        Forms\Components\Select::make('parent_id')
                            ->label('Job Scope')
                            ->options(\App\Models\Common\OfferingCategory::whereNull('parent_id')->pluck('name', 'id'))
                            ->live(),
                        Forms\Components\Select::make('child_id')
                            ->label('Job Scope Description')
                            ->options(
                                fn (Forms\Get $get) => $get('parent_id')
                                    ? \App\Models\Common\OfferingCategory::where('parent_id', $get('parent_id'))->pluck('name', 'id')
                                    : []
                            )
                            ->placeholder('All Descriptions')
                            ->visible(fn (Forms\Get $get) => filled($get('parent_id'))),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $parentId = $data['parent_id'] ?? null;
                        $childId = $data['child_id'] ?? null;
                        $categoryId = $childId ?: $parentId;

                        if (filled($categoryId)) {
                            $category = \App\Models\Common\OfferingCategory::find($categoryId);
                            if ($category) {
                                $categoryIds = \App\Models\Common\OfferingCategory::query()
                                    ->where($category->getLftName(), '>=', $category->getLft())
                                    ->where($category->getRgtName(), '<=', $category->getRgt())
                                    ->pluck('id');

                                return $query->whereHas(
                                    'categories',
                                    fn (Builder $q) => $q->whereIn('offering_categories.id', $categoryIds)
                                );
                            }
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['parent_id'] ?? null) {
                            $indicators[] = 'Job Scope: ' . \App\Models\Common\OfferingCategory::find($data['parent_id'])?->name;
                        }
                        if ($data['child_id'] ?? null) {
                            $indicators[] = 'Description: ' . \App\Models\Common\OfferingCategory::find($data['child_id'])?->name;
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->where('type', OfferingType::Service)->where('sellable', true));
        // You might want to filter further to only show those created via this resource if there's a distinction,
        // but for now "Services that are Sellable" seems like the definition of JobScopeOption based on request.
        // Actually, "JobScopeOption" is just a simplified view.
        // But verify if we need to distinguish them from other Services.
        // The user said "JobScopeOption resource which actually Offering... default offering type as Service, and sellable".
        // So filtering by Type::Service and Sellable::true seems appropriate for the list view to reduce noise if Products/Purchasable exist.
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
            'index' => Pages\ListJobScopeOptions::route('/'),
            'create' => Pages\CreateJobScopeOption::route('/create'),
            'edit' => Pages\EditJobScopeOption::route('/{record}/edit'),
        ];
    }
}

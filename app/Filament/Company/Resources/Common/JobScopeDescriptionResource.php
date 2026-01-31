<?php

namespace App\Filament\Company\Resources\Common;

use App\Filament\Company\Resources\Common\JobScopeDescriptionResource\Pages;
use App\Models\Common\OfferingCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JobScopeDescriptionResource extends Resource
{
    protected static ?string $model = OfferingCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Job Scope Descriptions';

    protected static ?string $modelLabel = 'Job Scope Description';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('parent_id')
                    ->label('Job Scope')
                    ->relationship('parent', 'name', fn (Builder $query) => $query->whereIsRoot())
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Job Scope')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent')
                    ->label('Job Scope')
                    ->relationship('parent', 'name', fn (Builder $query) => $query->whereIsRoot()),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('parent_id'));
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
            'index' => Pages\ListJobScopeDescriptions::route('/'),
            'create' => Pages\CreateJobScopeDescription::route('/create'),
            'edit' => Pages\EditJobScopeDescription::route('/{record}/edit'),
        ];
    }
}

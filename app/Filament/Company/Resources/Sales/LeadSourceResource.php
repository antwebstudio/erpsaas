<?php

namespace App\Filament\Company\Resources\Sales;

use App\Filament\Company\Resources\Sales\LeadSourceResource\Pages;
use App\Models\Common\LeadSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeadSourceResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = LeadSource::class;

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListLeadSources::route('/'),
            'create' => Pages\CreateLeadSource::route('/create'),
            'edit' => Pages\EditLeadSource::route('/{record}/edit'),
        ];
    }
}

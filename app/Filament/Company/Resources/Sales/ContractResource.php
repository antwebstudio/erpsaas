<?php

namespace App\Filament\Company\Resources\Sales;

use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EstimateStatus;
use App\Filament\Company\Resources\Sales\ContractResource\Pages\ViewContract;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages\ViewEstimate;
use App\Models\Accounting\Contract;
use App\Models\Accounting\Estimate;
use App\Scopes\CurrentCompanyScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class ContractResource extends Resource
{
    use \App\Filament\Traits\HasNavigationPermission;

    protected static ?string $model = Contract::class;

    public static function shouldRegisterNavigation(): bool
    {
        if (config('erp.hide_contract_in_navigation', false)) {
            return false;
        }

        return static::canViewAny();
    }


    protected static ?string $slug = 'sales/contracts';

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'Contract';

    protected static ?string $pluralModelLabel = 'Contracts';

    protected static ?string $navigationGroup = 'Sales';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                CurrentCompanyScope::class,
            ])
            ->where('status', EstimateStatus::Accepted)
            ->isNotTemplate();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')
                    ->label('Issuing Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('reference_number')
                    ->label('Reference Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('estimate_number')
                    ->label('Estimate Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total')
                    ->currencyWithConversion(static fn (Estimate $record) => $record->currency_code)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(static fn (Contract $record) => ViewContract::getUrl(['record' => $record])),
                Estimate::getDownloadMergedPdfAction(Tables\Actions\Action::class),
                static::getModel()::getPreviewAction(Tables\Actions\Action::class),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Company\Resources\Sales\ContractResource\Pages\ListContracts::route('/'),
            'view' => \App\Filament\Company\Resources\Sales\ContractResource\Pages\ViewContract::route('/{record}'),
        ];
    }
}

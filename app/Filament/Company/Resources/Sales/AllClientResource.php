<?php

namespace App\Filament\Company\Resources\Sales;

use App\Filament\Company\Resources\Sales\AllClientResource\Pages;
use App\Models\Common\AllClient;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AllClientResource extends ClientResource
{
    protected static ?string $model = AllClient::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'sales/all-clients';

    protected static ?string $navigationLabel = 'Clients';

    protected static ?string $modelLabel = 'Client';

    protected static ?string $pluralModelLabel = 'Clients';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny() && !parent::shouldRegisterNavigation();
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        $parentTable = parent::table($table);

        return $parentTable
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                ...$parentTable->getColumns(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('type', 'client');

        if (Auth::user()->can('view_mine_sales::all::client') && !Auth::user()->can('view_any_sales::all::client')) {
            $query->where('created_by', Auth::id());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAllClients::route('/'),
            'create' => Pages\CreateAllClient::route('/create'),
            'view' => Pages\ViewAllClient::route('/{record}'),
            'edit' => Pages\EditAllClient::route('/{record}/edit'),
        ];
    }
}

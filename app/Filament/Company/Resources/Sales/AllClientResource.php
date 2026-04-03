<?php

namespace App\Filament\Company\Resources\Sales;

use App\Filament\Company\Resources\Sales\AllClientResource\Pages;
use App\Models\Common\AllClient;

class AllClientResource extends ClientResource
{
    protected static ?string $model = AllClient::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'sales/all-clients';

    protected static ?string $navigationLabel = 'Clients';

    protected static ?string $modelLabel = 'Client';

    protected static ?string $pluralModelLabel = 'Clients';

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

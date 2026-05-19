<?php

namespace App\Filament\Company\Resources\Mail;

use JeffersonGoncalves\FilamentMail\Resources\MailLogResource as BaseMailLogResource;
use JeffersonGoncalves\FilamentMail\Resources\MailLogResource\Pages;

class MailLogResource extends BaseMailLogResource
{
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailLogs::route('/'),
            'view' => Pages\ViewMailLog::route('/{record}'),
        ];
    }
}

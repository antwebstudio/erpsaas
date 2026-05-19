<?php

namespace App\Filament\Company\Resources\Mail;

use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource as BaseMailTemplateResource;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource\Pages;

class MailTemplateResource extends BaseMailTemplateResource
{
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailTemplates::route('/'),
            'create' => Pages\CreateMailTemplate::route('/create'),
            'edit' => Pages\EditMailTemplate::route('/{record}/edit'),
            'view' => Pages\ViewMailTemplate::route('/{record}'),
        ];
    }
}

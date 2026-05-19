<?php

namespace App\Filament\Company\Resources\Mail;

use JeffersonGoncalves\FilamentMail\Resources\MailSuppressionResource as BaseMailSuppressionResource;
use JeffersonGoncalves\FilamentMail\Resources\MailSuppressionResource\Pages;

class MailSuppressionResource extends BaseMailSuppressionResource
{
    protected static ?string $tenantOwnershipRelationshipName = 'company';

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailSuppressions::route('/'),
            'create' => Pages\CreateMailSuppression::route('/create'),
        ];
    }
}

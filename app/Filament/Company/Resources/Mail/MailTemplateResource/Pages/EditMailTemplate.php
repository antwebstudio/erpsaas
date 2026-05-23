<?php

namespace App\Filament\Company\Resources\Mail\MailTemplateResource\Pages;

use App\Filament\Company\Resources\Mail\MailTemplateResource;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource\Pages\EditMailTemplate as BaseEditMailTemplate;

class EditMailTemplate extends BaseEditMailTemplate
{
    protected static string $resource = MailTemplateResource::class;
}

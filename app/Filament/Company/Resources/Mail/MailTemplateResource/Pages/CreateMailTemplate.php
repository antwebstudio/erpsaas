<?php

namespace App\Filament\Company\Resources\Mail\MailTemplateResource\Pages;

use App\Filament\Company\Resources\Mail\MailTemplateResource;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource\Pages\CreateMailTemplate as BaseCreateMailTemplate;

class CreateMailTemplate extends BaseCreateMailTemplate
{
    protected static string $resource = MailTemplateResource::class;
}

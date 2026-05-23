<?php

namespace App\Filament\Company\Resources\Mail\MailTemplateResource\Pages;

use App\Filament\Company\Resources\Mail\MailTemplateResource;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource\Pages\ViewMailTemplate as BaseViewMailTemplate;

class ViewMailTemplate extends BaseViewMailTemplate
{
    protected static string $resource = MailTemplateResource::class;
}

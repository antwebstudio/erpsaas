<?php

namespace App\Filament\Company\Resources\Mail\MailTemplateResource\Pages;

use App\Filament\Company\Resources\Mail\MailTemplateResource;
use JeffersonGoncalves\FilamentMail\Resources\MailTemplateResource\Pages\ListMailTemplates as BaseListMailTemplates;

class ListMailTemplates extends BaseListMailTemplates
{
    protected static string $resource = MailTemplateResource::class;
}

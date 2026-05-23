<?php

namespace App\Filament\Company\Resources\Mail\MailLogResource\Pages;

use App\Filament\Company\Resources\Mail\MailLogResource;
use JeffersonGoncalves\FilamentMail\Resources\MailLogResource\Pages\ViewMailLog as BaseViewMailLog;

class ViewMailLog extends BaseViewMailLog
{
    protected static string $resource = MailLogResource::class;
}

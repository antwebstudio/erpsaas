<?php

namespace App\Filament\Company\Pages;

use JeffersonGoncalves\FilamentMail\Pages\MailDashboard as BaseMailDashboard;

class MailDashboard extends BaseMailDashboard
{
    /**
     * Determine if the user can access this page.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->can('view_any_mail::mail::template') ||
            $user->can('view_any_mail::mail::log') ||
            $user->can('view_any_mail::mail::suppression')
        );
    }
}

<?php

namespace App\Filament\Company\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Wallo\FilamentCompanies\Pages\Company\CompanySettings;

class ManageCompany extends CompanySettings
{
    public static function getLabel(): string
    {
        return 'Manage Company';
    }

    public static function getSlug(): string
    {
        return 'manage-company';
    }

    public static function canView(Model $tenant): bool
    {
        return Auth::user()?->can('page_ManageCompany') ?? false;
    }
}

<?php

namespace App\Filament\Company\Pages;

use Filament\Pages\Page;

class WelcomePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.company.pages.welcome-page';

    protected static ?string $title = 'Welcome';

    protected static ?string $slug = ''; // Makes this the root page for the tenant

    protected static bool $shouldRegisterNavigation = false; // Hide from sidebar

    public function getCompanies()
    {
        /** @var \App\Models\User $user */
        $user = filament()->auth()->user();

        $companies = $user->allCompanies();

        if ($erpSystemCompanyId = config('erp.erp_system_company_id')) {
            $companies = $companies->reject(fn ($company) => $company->id == $erpSystemCompanyId);
        }

        return $companies;
    }

    public function getSystemCompany()
    {
        $erpSystemCompanyId = config('erp.erp_system_company_id');

        if (! $erpSystemCompanyId) {
            return null;
        }

        return \App\Models\Company::find($erpSystemCompanyId);
    }
}

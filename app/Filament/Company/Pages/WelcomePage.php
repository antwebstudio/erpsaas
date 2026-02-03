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
}

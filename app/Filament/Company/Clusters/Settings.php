<?php

namespace App\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class Settings extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }

    public static function getNavigationItems(): array
    {
        if (! static::canAccess()) {
            return [];
        }

        return parent::getNavigationItems();
    }
}

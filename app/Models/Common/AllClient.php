<?php

namespace App\Models\Common;

use App\Scopes\CurrentCompanyScope;
use Illuminate\Database\Eloquent\Builder;

class AllClient extends Client
{
    protected static function booted(): void
    {
        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', 'client');
        });
    }

    public static function bootCompanyOwned(): void
    {
        // Don't add CurrentCompanyScope in this model
    }

    public function estimates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return parent::estimates()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return parent::invoices()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }

    public function recurringInvoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return parent::recurringInvoices()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }

    public function variationOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return parent::variationOrders()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }

    public function contracts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return parent::contracts()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }
}

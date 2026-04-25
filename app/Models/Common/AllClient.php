<?php

namespace App\Models\Common;

use App\Scopes\CurrentCompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class AllClient extends Client
{
    public function getMorphClass(): string
    {
        return Client::class;
    }

    protected static function booted(): void
    {
        // Remove global scope to allow AllClientResource to show both clients and leads
    }

    public static function bootCompanyOwned(): void
    {
        // Don't add CurrentCompanyScope in this model
    }
    

    public function addresses(): MorphMany
    {
        return parent::addresses()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
            ;
    }

    public function billingAddress(): MorphOne
    {
        return parent::billingAddress()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }

    public function shippingAddress(): MorphOne
    {
        return parent::shippingAddress()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }

    public function contacts(): MorphMany
    {
        return parent::contacts()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
    }

    public function primaryContact(): MorphOne
    {
        return parent::primaryContact()->withoutGlobalScopes([
            CurrentCompanyScope::class,
        ]);
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

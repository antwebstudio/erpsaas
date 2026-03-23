<?php

namespace App\Models\Accounting;

class Contract extends Estimate
{
    protected $table = 'estimates';

    public function getMorphClass(): string
    {
        return Estimate::class;
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return parent::client()->withoutGlobalScopes();
    }

    public function clientAndLead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return parent::clientAndLead()->withoutGlobalScopes();
    }

    public function lineItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return parent::lineItems()->withoutGlobalScopes();
    }

    public function lineItemGroups(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return parent::lineItemGroups()->withoutGlobalScopes();
    }

    public function variationOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return parent::variationOrders()->withoutGlobalScopes();
    }

    public function getLabels(): \App\DTO\DocumentLabelDTO
    {
        return new \App\DTO\DocumentLabelDTO(
            title: 'Contract',
            number: 'Contract #',
            referenceNumber: 'Reference #',
            date: 'Date',
            dueDate: 'Expiry Date',
            amountDue: null,
        );
    }
}

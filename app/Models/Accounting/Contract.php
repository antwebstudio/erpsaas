<?php

namespace App\Models\Accounting;

use App\Collections\Accounting\DocumentCollection;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;

#[CollectedBy(DocumentCollection::class)]
class Contract extends Estimate
{
    protected $table = 'estimates';

    public function getMorphClass(): string
    {
        return Estimate::class;
    }

    public static function documentType(): \App\Enums\Accounting\DocumentType
    {
        return \App\Enums\Accounting\DocumentType::Contract;
    }

    public static function getNextDocumentNumber(?\App\Models\Company $company = null): string
    {
        $company ??= \Illuminate\Support\Facades\Auth::user()?->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $defaultContractSettings = $company->defaultContract;

        $numberPrefix = $defaultContractSettings->number_prefix ?? '';

        $latestDocument = static::query()
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class])
            ->where('company_id', $company->id)
            ->whereNotNull('reference_number')
            ->latest('id')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->reference_number, strlen($numberPrefix))
            : \App\Models\Setting\DocumentDefault::getBaseNumber();

        $numberNext = $lastNumberNumericPart + 1;

        if ($defaultContractSettings) {
            return $defaultContractSettings->getNumberNext(
                prefix: $numberPrefix,
                next: $numberNext
            );
        }

        return $numberPrefix . $numberNext;
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

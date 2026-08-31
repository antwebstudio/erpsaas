<?php

namespace App\Models\Common;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Common\AddressType;
use App\Enums\Common\ClientStatus;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\RecurringInvoice;
use App\Models\Accounting\Transaction;
use App\Models\Setting\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Client extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'clients';

    protected $fillable = [
        'company_id',
        'type',
        'status',
        'lead_source_id',
        'name',
        'nric',
        'currency_code',
        'account_number',
        'website',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => ClientStatus::class,
    ];

    protected static function booted(): void
    {
        if (static::class === Client::class) {
            static::addGlobalScope('type', function ($builder) {
                $builder->where('type', 'client');
            });
        }
    }

    public static function createWithRelations(array $data): self
    {
        /** @var Client $client */
        $client = static::create($data);

        if (isset($data['primaryContact']) && (
            filled($data['primaryContact']['first_name'] ?? null) ||
            filled($data['primaryContact']['last_name'] ?? null) ||
            filled($data['primaryContact']['email'] ?? null) ||
            ! empty($data['primaryContact']['phones'] ?? [])
        )) {
            $client->primaryContact()->create([
                'company_id' => $client->company_id,
                'is_primary' => true,
                'first_name' => $data['primaryContact']['first_name'] ?? '',
                'last_name' => $data['primaryContact']['last_name'] ?? '',
                'email' => $data['primaryContact']['email'] ?? null,
                'phones' => $data['primaryContact']['phones'] ?? [],
            ]);
        }

        if (isset($data['secondaryContacts'])) {
            foreach ($data['secondaryContacts'] as $contactData) {
                if (
                    filled($contactData['first_name'] ?? null) ||
                    filled($contactData['last_name'] ?? null) ||
                    filled($contactData['email'] ?? null) ||
                    ! empty($contactData['phones'] ?? [])
                ) {
                    $client->secondaryContacts()->create([
                        'company_id' => $client->company_id,
                        'is_primary' => false,
                        'first_name' => $contactData['first_name'] ?? '',
                        'last_name' => $contactData['last_name'] ?? '',
                        'email' => $contactData['email'] ?? null,
                        'phones' => $contactData['phones'] ?? [],
                    ]);
                }
            }
        }

        if (isset($data['billingAddress']) && (
            filled($data['billingAddress']['address_line_1'] ?? null) ||
            filled($data['billingAddress']['address_line_2'] ?? null) ||
            filled($data['billingAddress']['city'] ?? null) ||
            filled($data['billingAddress']['postal_code'] ?? null)
        )) {
            $client->billingAddress()->create([
                'company_id' => $client->company_id,
                'type' => AddressType::Billing,
                'address_line_1' => $data['billingAddress']['address_line_1'] ?? null,
                'address_line_2' => $data['billingAddress']['address_line_2'] ?? null,
                'country_code' => $data['billingAddress']['country_code'] ?? null,
                'state_id' => $data['billingAddress']['state_id'] ?? null,
                'city' => $data['billingAddress']['city'] ?? null,
                'postal_code' => $data['billingAddress']['postal_code'] ?? null,
            ]);
        }

        if (isset($data['shippingAddress'])) {
            $shippingData = $data['shippingAddress'];
            $shippingAddress = [
                'company_id' => $client->company_id,
                'type' => AddressType::Shipping,
                'recipient' => $shippingData['recipient'] ?? null,
                'phone' => $shippingData['phone'] ?? null,
                'notes' => $shippingData['notes'] ?? null,
            ];

            if ($shippingData['same_as_billing'] ?? false) {
                $billingAddress = $client->billingAddress;
                if ($billingAddress) {
                    $shippingAddress = [
                        ...$shippingAddress,
                        'parent_address_id' => $billingAddress->id,
                        'address_line_1' => $billingAddress->address_line_1,
                        'address_line_2' => $billingAddress->address_line_2,
                        'country_code' => $billingAddress->country_code,
                        'state_id' => $billingAddress->state_id,
                        'city' => $billingAddress->city,
                        'postal_code' => $billingAddress->postal_code,
                    ];
                    $client->shippingAddress()->create($shippingAddress);
                }
            } elseif (
                filled($shippingData['address_line_1'] ?? null) ||
                filled($shippingData['address_line_2'] ?? null) ||
                filled($shippingData['city'] ?? null) ||
                filled($shippingData['postal_code'] ?? null)
            ) {
                $shippingAddress = [
                    ...$shippingAddress,
                    'address_line_1' => $shippingData['address_line_1'] ?? null,
                    'address_line_2' => $shippingData['address_line_2'] ?? null,
                    'country_code' => $shippingData['country_code'] ?? null,
                    'state_id' => $shippingData['state_id'] ?? null,
                    'city' => $shippingData['city'] ?? null,
                    'postal_code' => $shippingData['postal_code'] ?? null,
                ];

                $client->shippingAddress()->create($shippingAddress);
            }
        }

        return $client;
    }

    public function updateWithRelations(array $data): self
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $this->update($data);

            if (isset($data['primaryContact']) && (
                filled($data['primaryContact']['first_name'] ?? null) ||
                filled($data['primaryContact']['last_name'] ?? null) ||
                filled($data['primaryContact']['email'] ?? null) ||
                ! empty($data['primaryContact']['phones'] ?? [])
            )) {
                $this->primaryContact()->updateOrCreate(
                    ['is_primary' => true],
                    [
                        'company_id' => $this->company_id,
                        'first_name' => $data['primaryContact']['first_name'] ?? '',
                        'last_name' => $data['primaryContact']['last_name'] ?? '',
                        'email' => $data['primaryContact']['email'] ?? null,
                        'phones' => $data['primaryContact']['phones'] ?? [],
                    ]
                );
            }

            if (isset($data['secondaryContacts'])) {
                // Delete removed contacts
                $existingIds = collect($data['secondaryContacts'])->pluck('id')->filter()->all();
                $this->secondaryContacts()->whereNotIn('id', $existingIds)->delete();

                // Update or create contacts
                foreach ($data['secondaryContacts'] as $contactData) {
                    if (
                        filled($contactData['first_name'] ?? null) ||
                        filled($contactData['last_name'] ?? null) ||
                        filled($contactData['email'] ?? null) ||
                        ! empty($contactData['phones'] ?? [])
                    ) {
                        $this->secondaryContacts()->updateOrCreate(
                            ['id' => $contactData['id'] ?? null],
                            [
                                'company_id' => $this->company_id,
                                'is_primary' => false,
                                'first_name' => $contactData['first_name'] ?? '',
                                'last_name' => $contactData['last_name'] ?? '',
                                'email' => $contactData['email'] ?? null,
                                'phones' => $contactData['phones'] ?? [],
                            ]
                        );
                    }
                }
            }

            if (isset($data['billingAddress']) && (
                filled($data['billingAddress']['address_line_1'] ?? null) ||
                filled($data['billingAddress']['address_line_2'] ?? null) ||
                filled($data['billingAddress']['city'] ?? null) ||
                filled($data['billingAddress']['postal_code'] ?? null)
            )) {
                $this->billingAddress()->updateOrCreate(
                    ['type' => AddressType::Billing],
                    [
                        'company_id' => $this->company_id,
                        'address_line_1' => $data['billingAddress']['address_line_1'] ?? null,
                        'address_line_2' => $data['billingAddress']['address_line_2'] ?? null,
                        'country_code' => $data['billingAddress']['country_code'] ?? null,
                        'state_id' => $data['billingAddress']['state_id'] ?? null,
                        'city' => $data['billingAddress']['city'] ?? null,
                        'postal_code' => $data['billingAddress']['postal_code'] ?? null,
                    ]
                );
            }

            if (isset($data['shippingAddress'])) {
                $shippingData = $data['shippingAddress'];

                if ($shippingData['same_as_billing'] ?? false) {
                    $billingAddress = $this->billingAddress;
                    if ($billingAddress) {
                        $shippingAddress = [
                            'company_id' => $this->company_id,
                            'type' => AddressType::Shipping,
                            'recipient' => $shippingData['recipient'] ?? null,
                            'phone' => $shippingData['phone'] ?? null,
                            'notes' => $shippingData['notes'] ?? null,
                            'parent_address_id' => $billingAddress->id,
                            'address_line_1' => $billingAddress->address_line_1,
                            'address_line_2' => $billingAddress->address_line_2,
                            'country_code' => $billingAddress->country_code,
                            'state_id' => $billingAddress->state_id,
                            'city' => $billingAddress->city,
                            'postal_code' => $billingAddress->postal_code,
                        ];

                        $this->shippingAddress()->updateOrCreate(
                            ['type' => AddressType::Shipping],
                            $shippingAddress
                        );
                    }
                } elseif (
                    filled($shippingData['address_line_1'] ?? null) ||
                    filled($shippingData['address_line_2'] ?? null) ||
                    filled($shippingData['city'] ?? null) ||
                    filled($shippingData['postal_code'] ?? null)
                ) {
                    $shippingAddress = [
                        'company_id' => $this->company_id,
                        'type' => AddressType::Shipping,
                        'recipient' => $shippingData['recipient'] ?? null,
                        'phone' => $shippingData['phone'] ?? null,
                        'notes' => $shippingData['notes'] ?? null,
                        'parent_address_id' => null,
                        'address_line_1' => $shippingData['address_line_1'] ?? null,
                        'address_line_2' => $shippingData['address_line_2'] ?? null,
                        'country_code' => $shippingData['country_code'] ?? null,
                        'state_id' => $shippingData['state_id'] ?? null,
                        'city' => $shippingData['city'] ?? null,
                        'postal_code' => $shippingData['postal_code'] ?? null,
                    ];

                    $this->shippingAddress()->updateOrCreate(
                        ['type' => AddressType::Shipping],
                        $shippingAddress
                    );
                }
            }
        });

        return $this;
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'payeeable');
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable')
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function primaryContact(): MorphOne
    {
        return $this->morphOne(Contact::class, 'contactable')
            ->where('is_primary', true)
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function secondaryContacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable')
            ->where('is_primary', false)
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable')
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function billingAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')
            ->where('type', AddressType::Billing)
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function shippingAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')
            ->where('type', AddressType::Shipping)
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'client_id')
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'client_id')
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class, 'client_id')
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function variationOrders(): HasMany
    {
        return $this->hasMany(\App\Models\Accounting\VariationOrder::class, 'client_id')
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(\App\Models\Accounting\Contract::class, 'client_id')
            ->whereIn('status', [\App\Enums\Accounting\EstimateStatus::Accepted, \App\Enums\Accounting\EstimateStatus::Completed])
            ->isNotTemplate()
            ->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);
    }

    /**
     * Sum of this client's contract totals (accepted/completed estimates only),
     * converted to the company's default currency.
     */
    public function getContractTotal(): int
    {
        return $this->contracts()->get()->sumMoneyInDefaultCurrency('total');
    }

    /**
     * Sum of this client's approved variation order totals (which may be
     * negative, reducing the contract value), converted to the company's
     * default currency. Draft, sent, and rejected variation orders are
     * excluded since they haven't been formally agreed with the client.
     */
    public function getApprovedVariationOrderTotal(): int
    {
        return $this->variationOrders()
            ->where('status', \App\Enums\Accounting\VariationOrderStatus::Approved)
            ->get()
            ->sumMoneyInDefaultCurrency('total');
    }

    /**
     * The client's running contract value: the original contract total plus
     * all approved variation orders (additions and deductions) to date.
     */
    public function getAdjustedContractTotal(): int
    {
        return $this->getContractTotal() + $this->getApprovedVariationOrderTotal();
    }

    /**
     * Sum of amounts paid across this client's invoices, converted to the
     * company's default currency.
     */
    public function getTotalPaymentReceived(): int
    {
        return $this->invoices()->get()->sumMoneyInDefaultCurrency('amount_paid');
    }

    /**
     * Percentage of the adjusted contract total that has been paid so far.
     * Returns null when there is no contract value to measure against.
     */
    public function getPaymentReceivedPercentage(): ?float
    {
        $adjustedContractTotal = $this->getAdjustedContractTotal();

        if ($adjustedContractTotal <= 0) {
            return null;
        }

        return ($this->getTotalPaymentReceived() / $adjustedContractTotal) * 100;
    }
}

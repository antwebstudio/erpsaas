<?php

namespace App\Services\Accounting;

use App\Enums\Accounting\EstimateStatus;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\VariationOrder;
use App\Models\Accounting\Transaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\RecurringInvoice;
use Illuminate\Support\Facades\DB;

class EstimateService
{
    /**
     * Convert an estimate to a contract.
     * Including updating client, contact, and address company scoping.
     *
     * @param Estimate $estimate
     * @param array $data Additional data to update (missing fields collected from form)
     * @return void
     */
    public function convertToContract(Estimate $estimate, array $data = []): void
    {
        DB::transaction(function () use ($estimate, $data) {
            // 1. Update missing estimate details
            if (isset($data['estimate_number'])) {
                $estimate->estimate_number = $data['estimate_number'];
            }
            if (isset($data['date'])) {
                $estimate->date = $data['date'];
            }
            if (isset($data['reference_number'])) {
                $estimate->reference_number = $data['reference_number'];
            }

            if (empty($estimate->reference_number)) {
                $estimate->reference_number = \App\Models\Accounting\Contract::getNextDocumentNumber($estimate->company);
            }

            // 2. Update Client and its relations (Contacts, Addresses)
            /** @var \App\Models\Common\Client|null $client */
            $client = \App\Models\Common\Client::withoutGlobalScopes()->find($estimate->client_id);
            if ($client) {
                // Determine if we need to convert morph types from Lead to Client
                if ($client->type === 'lead') {
                    $leadMorphClass = (new \App\Models\Common\Lead)->getMorphClass();
                    $clientMorphClass = $client->getMorphClass();

                    if ($leadMorphClass !== $clientMorphClass) {
                        \App\Models\Common\Contact::withoutGlobalScopes()
                            ->where('contactable_type', $leadMorphClass)
                            ->where('contactable_id', $client->id)
                            ->update(['contactable_type' => $clientMorphClass]);

                        \App\Models\Common\Address::withoutGlobalScopes()
                            ->where('addressable_type', $leadMorphClass)
                            ->where('addressable_id', $client->id)
                            ->update(['addressable_type' => $clientMorphClass]);

                        \App\Models\Accounting\Transaction::withoutGlobalScopes()
                            ->where('payeeable_type', $leadMorphClass)
                            ->where('payeeable_id', $client->id)
                            ->update(['payeeable_type' => $clientMorphClass]);

                        \App\Models\Accounting\Transaction::withoutGlobalScopes()
                            ->where('transactionable_type', $leadMorphClass)
                            ->where('transactionable_id', $client->id)
                            ->update(['transactionable_type' => $clientMorphClass]);
                    }
                }
                $clientUpdate = [];
                if (isset($data['client_name'])) $clientUpdate['name'] = $data['client_name'];
                if (isset($data['client_nric'])) $clientUpdate['nric'] = $data['client_nric'];

                if (!empty($clientUpdate)) {
                    $client->update($clientUpdate);
                }

                // Update Primary Contact
                if (isset($data['client_phone']) || isset($data['client_email'])) {
                    /** @var \App\Models\Common\Contact|null $primaryContact */
                    $primaryContact = $client->primaryContact()->withoutGlobalScopes()->first();
                    $contactUpdate = [];
                    if (isset($data['client_email'])) $contactUpdate['email'] = $data['client_email'];
                    if (isset($data['client_phone'])) {
                        $phones = $primaryContact?->phones ?? [];
                        $found = false;
                        foreach ($phones as &$phone) {
                            if (($phone['type'] ?? '') === 'primary') {
                                $phone['data']['number'] = $data['client_phone'];
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $phones[] = ['type' => 'primary', 'data' => ['number' => $data['client_phone']]];
                        }
                        $contactUpdate['phones'] = $phones;
                    }

                    if ($primaryContact) {
                        $primaryContact->update($contactUpdate);
                    } else {
                        $client->primaryContact()->create(array_merge($contactUpdate, [
                            'company_id' => $client->company_id,
                            'is_primary' => true,
                            'first_name' => $data['client_name'] ?? $client->name,
                        ]));
                    }
                }

                // Update Billing Address
                if (isset($data['client_address_line_1']) || isset($data['client_postal_code'])) {
                    /** @var \App\Models\Common\Address|null $billingAddress */
                    $billingAddress = $client->billingAddress()->withoutGlobalScopes()->first();
                    $addressUpdate = [];
                    if (isset($data['client_address_line_1'])) $addressUpdate['address_line_1'] = $data['client_address_line_1'];
                    if (isset($data['client_address_line_2'])) $addressUpdate['address_line_2'] = $data['client_address_line_2'];
                    if (isset($data['client_postal_code'])) $addressUpdate['postal_code'] = $data['client_postal_code'];
                    if (isset($data['client_country_code'])) $addressUpdate['country_code'] = $data['client_country_code'];

                    if ($billingAddress) {
                        $billingAddress->update($addressUpdate);
                    } else {
                        $client->billingAddress()->create(array_merge($addressUpdate, [
                            'company_id' => $client->company_id,
                            'type' => \App\Enums\Common\AddressType::Billing,
                        ]));
                    }
                }
            }

            // 3. Update Salesperson (Issuer)
            if (isset($data['salesperson_name']) || isset($data['salesperson_email'])) {
                $createdBy = $estimate->createdBy;
                if ($createdBy) {
                    $userUpdate = [];
                    if (isset($data['salesperson_name'])) $userUpdate['name'] = $data['salesperson_name'];
                    if (isset($data['salesperson_email'])) $userUpdate['email'] = $data['salesperson_email'];
                    $createdBy->update($userUpdate);
                }
            }

            // 4. Final Conversion and Company Scoping Update
            $newCompanyId = $estimate->template_company_id ?? $estimate->company_id;

            // Update Estimate Status only — company_id must not change on conversion
            $estimate->forceFill([
                'status' => EstimateStatus::Accepted,
                'accepted_at' => company_now(),
            ]);


            $estimate->save();

            // 5. Update All Client-related Data Company Scoping
            if ($client) {
                // Update Client itself
                $client->update(['company_id' => $newCompanyId, 'type' => 'client']);

                // Update Contacts and Addresses
                $client->contacts()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                $client->addresses()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);

                // Update All Transactions where client is the payee
                $client->transactions()->withoutGlobalScopes()->each(function (Transaction $transaction) use ($newCompanyId) {
                    $transaction->update(['company_id' => $newCompanyId]);
                    $transaction->journalEntries()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                });

                // Update All Invoices for this client
                $client->invoices()->withoutGlobalScopes()->each(function (Invoice $invoice) use ($newCompanyId) {
                    $invoice->update(['company_id' => $newCompanyId]);
                    $invoice->lineItemGroups()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                    $invoice->lineItems()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);

                    // Update transactions linked to this invoice (e.g. payments)
                    $invoice->transactions()->withoutGlobalScopes()->each(function (Transaction $transaction) use ($newCompanyId) {
                        $transaction->update(['company_id' => $newCompanyId]);
                        $transaction->journalEntries()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                    });
                });

                // Update All Recurring Invoices
                $client->recurringInvoices()->withoutGlobalScopes()->each(function (RecurringInvoice $ri) use ($newCompanyId) {
                    $ri->update(['company_id' => $newCompanyId]);
                    $ri->lineItemGroups()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                    $ri->lineItems()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                });

                // Update All Variation Orders for this client
                $client->variationOrders()->withoutGlobalScopes()->each(function (VariationOrder $vo) use ($newCompanyId) {
                    $vo->update(['company_id' => $newCompanyId]);
                    $vo->lineItemGroups()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                    $vo->lineItems()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);

                    // Transactions linked to VO (if any)
                    if (method_exists($vo, 'transactions')) {
                        $vo->transactions()->withoutGlobalScopes()->each(function (Transaction $transaction) use ($newCompanyId) {
                            $transaction->update(['company_id' => $newCompanyId]);
                            $transaction->journalEntries()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                        });
                    }
                });

                // Update All Estimates for this client (including the current one is handled via $estimate variable)
                $client->estimates()->withoutGlobalScopes()->where('id', '!=', $estimate->id)->each(function (Estimate $e) use ($newCompanyId) {
                    $e->update(['company_id' => $newCompanyId]);
                    $e->lineItemGroups()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                    $e->lineItems()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                });
            }

            // Update Current Estimate Related Items (redundant but explicit)
            $estimate->lineItemGroups()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
            $estimate->lineItems()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
            
            // Transactions linked to this estimate
            if (method_exists($estimate, 'transactions')) {
                $estimate->transactions()->withoutGlobalScopes()->each(function (Transaction $transaction) use ($newCompanyId) {
                    $transaction->update(['company_id' => $newCompanyId]);
                    $transaction->journalEntries()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                });
            }
        });
    }
}

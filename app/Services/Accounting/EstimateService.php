<?php

namespace App\Services\Accounting;

use App\Enums\Accounting\EstimateStatus;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\VariationOrder;
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
            /** @var \App\Models\Common\Client $client */
            $client = $estimate->client()->withoutGlobalScopes()->first();
            if ($client) {
                $clientUpdate = [];
                if (isset($data['client_name'])) $clientUpdate['name'] = $data['client_name'];
                if (isset($data['client_nric'])) $clientUpdate['nric'] = $data['client_nric'];

                if (!empty($clientUpdate)) {
                    $client->update($clientUpdate);
                }

                // Update Primary Contact
                if (isset($data['client_phone']) || isset($data['client_email'])) {
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

            // Update Estimate Status and Company
            $estimate->forceFill([
                'status' => EstimateStatus::Accepted,
                'accepted_at' => company_now(),
                'company_id' => $newCompanyId,
            ]);

            if (isset($data['estimate_number'])) {
                $estimate->estimate_number = $data['estimate_number'];
            }
            if (isset($data['date'])) {
                $estimate->date = $data['date'];
            }
            
            $estimate->save();

            // 5. Update Client Company Scoping (MUST use withoutGlobalScopes to bypass multi-tenancy filters)
            if ($client) {
                $client->update(['company_id' => $newCompanyId, 'type' => 'client']);
                $client->contacts()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                $client->addresses()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
            }

            // Update Related Line Items and Variation Orders
            $estimate->lineItemGroups()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
            $estimate->lineItems()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);

            $estimate->variationOrders()->withoutGlobalScopes()->each(function (VariationOrder $vo) use ($newCompanyId) {
                $vo->update(['company_id' => $newCompanyId]);
                $vo->lineItemGroups()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
                $vo->lineItems()->withoutGlobalScopes()->update(['company_id' => $newCompanyId]);
            });
        });
    }
}

<?php

namespace App\Observers;

use App\Enums\Common\AddressType;
use App\Jobs\SyncContactToMailchimp;
use App\Models\Common\Address;
use App\Models\Common\Client;
use App\Models\Common\Lead;

class MailchimpBillingAddressObserver
{
    public function created(Address $address): void
    {
        $this->dispatchIfClientBillingAddress($address);
    }

    public function updated(Address $address): void
    {
        $this->dispatchIfClientBillingAddress($address);
    }

    private function dispatchIfClientBillingAddress(Address $address): void
    {
        if ($address->type !== AddressType::Billing) {
            return;
        }

        // Bypass CurrentCompanyScope — AllClientResource is cross-company, so the session
        // company may not match the address's company, causing morphTo() to return null.
        if (! in_array($address->addressable_type, [Client::class, Lead::class])) {
            return;
        }

        $client = Client::withoutGlobalScopes()->find($address->addressable_id);

        if ($client) {
            SyncContactToMailchimp::dispatch($client);
        }
    }
}

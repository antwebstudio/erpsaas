<?php

namespace App\Observers;

use App\Jobs\SyncContactToMailchimp;
use App\Models\Common\Client;
use App\Models\Common\Contact;

class MailchimpPrimaryContactObserver
{
    public function created(Contact $contact): void
    {
        $this->dispatchIfPrimaryClientContact($contact);
    }

    public function updated(Contact $contact): void
    {
        if (! $contact->is_primary) {
            return;
        }

        $client = $this->resolveClient($contact);

        if ($client) {
            $previousEmail = $contact->wasChanged('email') ? $contact->getOriginal('email') : null;
            SyncContactToMailchimp::dispatch($client, $previousEmail);
        }
    }

    private function dispatchIfPrimaryClientContact(Contact $contact): void
    {
        if (! $contact->is_primary) {
            return;
        }

        $client = $this->resolveClient($contact);

        if ($client) {
            SyncContactToMailchimp::dispatch($client);
        }
    }

    private function resolveClient(Contact $contact): ?Client
    {
        // contacts can belong to Client, Lead, or AllClient — all stored in the clients table.
        // We bypass CurrentCompanyScope here because AllClientResource is cross-company and
        // the session company may not match the contact's company, which would cause the
        // normal morphTo() to return null and silently skip the sync.
        if (! in_array($contact->contactable_type, [Client::class, Lead::class])) {
            return null;
        }

        return Client::withoutGlobalScopes()->find($contact->contactable_id);
    }
}

<?php

namespace App\Observers;

use App\Jobs\SyncContactToMailchimp;
use App\Jobs\UnsubscribeMailchimpContact;
use App\Models\Common\Client;

class MailchimpContactObserver
{
    public function created(Client $client): void
    {
        SyncContactToMailchimp::dispatch($client);
    }

    public function updated(Client $client): void
    {
        \Log::debug('mailchimp contact updated');
        SyncContactToMailchimp::dispatch($client);
    }

    public function replicated(Client $client): void
    {
        SyncContactToMailchimp::dispatch($client);
    }

    public function deleting(Client $client): void
    {
        $email = $client->primaryContact?->email;

        if ($email) {
            UnsubscribeMailchimpContact::dispatch($email);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Common\Client;
use App\Models\Common\Lead;
use App\Services\MailchimpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncLeadsAndClientsToMailchimp implements ShouldQueue
{
    use Queueable;

    public function handle(MailchimpService $mailchimp): void
    {
        $all = Client::with(['primaryContact', 'billingAddress'])->get()
            ->merge(Lead::with(['primaryContact', 'billingAddress'])->get());

        foreach ($all as $client) {
            $contact = $client->primaryContact;

            if ($contact && $contact->email) {
                $mailchimp->syncContact($client, $contact);
            }
        }
    }
}

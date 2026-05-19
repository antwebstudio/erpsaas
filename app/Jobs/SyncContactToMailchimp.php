<?php

namespace App\Jobs;

use App\Models\Common\Client;
use App\Services\MailchimpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SyncContactToMailchimp implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Client $client) {}

    public function handle(MailchimpService $mailchimp): void
    {
        if (! $mailchimp->isConfigured()) {
            \Log::debug('mailchimp is not configured');
            return;
        }

        $this->client->loadMissing(['primaryContact', 'billingAddress']);

        $contact = $this->client->primaryContact;
        \Log::debug('mailchimp contact', [$contact, $contact->email]);
        if ($contact && $contact->email) {
            \Log::debug('sync mailchimp contact', [$contact]);
            $mailchimp->syncContact($this->client, $contact);
        }
    }
}

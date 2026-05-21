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

    public function __construct(
        public readonly Client $client,
        public readonly ?string $previousEmail = null,
    ) {}

    public function handle(MailchimpService $mailchimp): void
    {
        if (! $mailchimp->isConfigured()) {
            return;
        }

        $this->client->loadMissing(['primaryContact', 'billingAddress.state']);

        $contact = $this->client->primaryContact;

        if ($contact && $contact->email) {
            $mailchimp->syncContact($this->client, $contact, $this->previousEmail);
        }
    }
}

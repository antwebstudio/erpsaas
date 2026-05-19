<?php

namespace App\Jobs;

use App\Services\MailchimpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UnsubscribeMailchimpContact implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $email) {}

    public function handle(MailchimpService $mailchimp): void
    {
        if (! $mailchimp->isConfigured()) {
            return;
        }

        $mailchimp->unsubscribeContact($this->email);
    }
}

<?php

namespace App\Jobs;

use App\Services\MailchimpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteMailchimpTemplate implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $templateName) {}

    public function handle(MailchimpService $mailchimp): void
    {
        if (! $mailchimp->isConfigured()) {
            return;
        }

        $mailchimp->deleteTemplateByName($this->templateName);
    }
}

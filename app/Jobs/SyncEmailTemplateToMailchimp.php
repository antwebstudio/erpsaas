<?php

namespace App\Jobs;

use App\Models\Mail\MailTemplate;
use App\Services\MailchimpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SyncEmailTemplateToMailchimp implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly MailTemplate $template) {}

    public function handle(MailchimpService $mailchimp): void
    {
        if (! $mailchimp->isConfigured()) {
            return;
        }

        $mailchimp->syncEmailTemplate($this->template);
    }
}

<?php

namespace App\Jobs;

use App\Models\Mail\MailTemplate;
use App\Services\MailchimpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncEmailTemplatesToMailchimp implements ShouldQueue
{
    use Queueable;

    public function handle(MailchimpService $mailchimp): void
    {
        MailTemplate::query()
            ->where('is_active', true)
            ->each(function (MailTemplate $template) use ($mailchimp) {
                $mailchimp->syncEmailTemplate($template);
            });
    }
}

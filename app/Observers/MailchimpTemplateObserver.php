<?php

namespace App\Observers;

use App\Jobs\DeleteMailchimpTemplate;
use App\Jobs\SyncEmailTemplateToMailchimp;
use App\Models\Mail\MailTemplate;

class MailchimpTemplateObserver
{
    public function created(MailTemplate $template): void
    {
        SyncEmailTemplateToMailchimp::dispatch($template);
    }

    public function updated(MailTemplate $template): void
    {
        SyncEmailTemplateToMailchimp::dispatch($template);
    }

    public function replicated(MailTemplate $template): void
    {
        SyncEmailTemplateToMailchimp::dispatch($template);
    }

    public function deleting(MailTemplate $template): void
    {
        DeleteMailchimpTemplate::dispatch($template->name);
    }
}

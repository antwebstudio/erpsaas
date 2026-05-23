<?php

namespace App\Jobs;

use App\Models\Mail\MailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Mail\TemplateNotificationMailable;

class SendTemplateEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        public readonly string $email,
        public readonly string $recipientName,
        public readonly string $templateId,
        public readonly array $variables = [],
    ) {}

    public function handle(): void
    {
        $template = MailTemplate::find($this->templateId);

        if (! $template || ! $template->is_active) {
            return;
        }

        $mailable = (new TemplateNotificationMailable('', $this->variables))
            ->useTemplate($template);

        Mail::to($this->email, $this->recipientName)->send($mailable);
    }
}

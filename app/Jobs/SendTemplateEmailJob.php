<?php

namespace App\Jobs;

use App\Mail\TemplateEmailMailable;
use App\Models\Mail\MailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTemplateEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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

        if (isset($this->variables['client_id'])) {
            $client = \App\Models\Common\Client::withoutGlobalScope('type')
                ->find($this->variables['client_id']);
            if ($client) {
                $template->replaceVariablesForClient($client);
            }
        }

        $mailable = (new TemplateEmailMailable('', $this->variables))
            ->useTemplate($template);

        Mail::to($this->email, $this->recipientName)->send($mailable);
    }
}

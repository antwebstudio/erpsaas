<?php

namespace App\Console\Commands;

use App\Models\Mail\MailTemplate;
use App\Services\MailchimpService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class SyncEmailTemplatesToMailchimp extends Command
{
    protected $signature = 'mailchimp:sync-email-templates';

    protected $description = 'Sync active email templates to Mailchimp';

    public function handle(MailchimpService $mailchimp): int
    {
        $templates = MailTemplate::query()
            ->where('is_active', true)
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($templates as $template) {
            try {
                $mailchimp->syncEmailTemplate($template);
                $synced++;
                $this->line("  <info>✓</info> {$template->name} ({$template->key})");
            } catch (RequestException $e) {
                $failed++;
                $this->line("  <error>✗</error> {$template->name} — {$e->response?->json('detail', $e->getMessage())}");
            }
        }

        $this->newLine();
        $this->info("Done. Synced: {$synced}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

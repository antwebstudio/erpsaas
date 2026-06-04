<?php

namespace App\Console\Commands;

use App\Models\Common\Client;
use App\Models\Common\Lead;
use App\Services\MailchimpService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class SyncContactsToMailchimp extends Command
{
    protected $signature = 'mailchimp:sync-contacts';

    protected $description = 'Sync leads and clients to Mailchimp audience';

    public function handle(MailchimpService $mailchimp): int
    {
        if (! $mailchimp->hasApiKey()) {
            $this->error('MAILCHIMP_API_KEY is not set in your .env file.');

            return self::FAILURE;
        }

        $list = $this->resolveContactsList($mailchimp);

        if (! $list) {
            return self::FAILURE;
        }

        $this->info("Syncing to Mailchimp list: \"{$list['name']}\" (ID: {$list['id']})");
        $this->newLine();

        $clients = Client::with(['primaryContact', 'billingAddress'])->get();
        $leads = Lead::with(['primaryContact', 'billingAddress'])->get();
        $clients = $clients->merge($leads);

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            $contact = $client->primaryContact;

            if (! $contact || empty($contact->email)) {
                $skipped++;

                continue;
            }

            try {
                $member = $mailchimp->syncContact($client, $contact);

                if (isset($member['status']) && $member['status'] === 'subscribed') {
                    $this->line("  <info>✓</info> [{$member['status']}] {$member['email_address']} — Mailchimp ID: {$member['id']}");
                } elseif (! empty($member)) {
                    $this->line("  <comment>~</comment> [{$member['status']}] {$member['email_address']} — Mailchimp ID: {$member['id']}");
                }

                $synced++;
            } catch (RuntimeException $e) {
                $skipped++;
                $this->line("  <comment>⚠</comment> {$contact->email} — skipped: {$e->getMessage()}");
            } catch (RequestException $e) {
                $failed++;
                $body = $e->response?->json();
                $detail = $body['detail'] ?? $e->getMessage();
                $errors = $body['errors'] ?? [];
                $this->line("  <error>✗</error> {$contact->email} — {$detail}");
                foreach ($errors as $err) {
                    $this->line("      field={$err['field']} message={$err['message']}");
                }
            }
        }

        $this->newLine();
        $this->info("Done. Synced: {$synced}, Failed: {$failed}, Skipped (no email): {$skipped}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveContactsList(MailchimpService $mailchimp): ?array
    {
        if ($mailchimp->isConfigured()) {
            $list = $mailchimp->fetchContactsList();

            if ($list) {
                return $list;
            }

            $this->warn('Configured list ID not found. Fetching available Mailchimp audiences...');
        }

        return $this->pickListFromMailchimp($mailchimp);
    }

    private function pickListFromMailchimp(MailchimpService $mailchimp): ?array
    {
        try {
            $lists = $mailchimp->fetchAllLists();
        } catch (RequestException $e) {
            $this->error('Failed to fetch Mailchimp audiences: ' . $e->response?->json('detail', $e->getMessage()));

            return null;
        }

        if (empty($lists)) {
            $this->error('No audiences found in your Mailchimp account. Create one at mailchimp.com and set MAILCHIMP_CONTACTS_LIST_ID in your .env file.');

            return null;
        }

        if (count($lists) === 1) {
            $list = $lists[0];
            $mailchimp->useList($list['id']);
            $this->warn("Add this to your .env to skip this step: MAILCHIMP_CONTACTS_LIST_ID={$list['id']}");

            return $list;
        }

        $this->info('Multiple Mailchimp audiences found:');

        $choices = collect($lists)->mapWithKeys(fn ($l) => [
            $l['id'] => "{$l['name']} (ID: {$l['id']}, members: {$l['stats']['member_count']})",
        ])->all();

        $selected = $this->choice('Select the audience to sync contacts into', array_values($choices));
        $listId = array_search($selected, $choices);
        $list = collect($lists)->firstWhere('id', $listId);

        $mailchimp->useList($listId);
        $this->warn("Add this to your .env to skip this step: MAILCHIMP_CONTACTS_LIST_ID={$listId}");

        return $list;
    }
}

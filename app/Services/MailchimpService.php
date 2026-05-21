<?php

namespace App\Services;

use App\Models\Common\Client;
use App\Models\Common\Contact;
use App\Models\Mail\MailTemplate;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class MailchimpService
{
    protected ?string $apiKey;

    protected ?string $serverPrefix;

    protected ?string $contactsListId;

    protected string $baseUrl;

    protected HttpClient $client;

    protected ?array $mergeFieldTags = null;

    public function __construct(HttpClient $client, Config $config)
    {
        $this->client = $client;
        $this->apiKey = $config->get('mailchimp.api_key');
        $this->serverPrefix = $config->get('mailchimp.server_prefix', 'us1');
        $this->contactsListId = $config->get('mailchimp.lists.contacts');
        $this->baseUrl = "https://{$this->serverPrefix}.api.mailchimp.com/3.0";
    }

    /**
     * Sync a contact to Mailchimp and verify via a follow-up GET.
     *
     * @return array{email_address: string, status: string, id: string}  Member data from Mailchimp.
     * @throws \RuntimeException  When a required merge field cannot be populated.
     */
    public function syncContact(Client $client, Contact $contact, ?string $previousEmail = null): array
    {
        $emailChanged = $previousEmail && $previousEmail !== $contact->email;
        $audienceTag = $client->type === 'lead' ? 'lead' : 'client';

        if ($emailChanged) {
            $oldHash = md5(strtolower($previousEmail));
            try {
                // Archive the old subscriber so Mailchimp doesn't reject the new email as a duplicate.
                $this->http()->delete("{$this->baseUrl}/lists/{$this->contactsListId}/members/{$oldHash}");
                Log::info('Mailchimp: archived old subscriber after email change', [
                    'contact_id' => $contact->id,
                    'old_email' => $previousEmail,
                ]);
            } catch (\Exception $e) {
                // Old subscriber may not exist in Mailchimp — that's fine, continue with upsert.
                Log::debug('Mailchimp: old subscriber not found during email change, skipping archive', [
                    'contact_id' => $contact->id,
                    'old_email' => $previousEmail,
                ]);
            }
        }

        $subscriberHash = md5(strtolower($contact->email));

        Log::info('Mailchimp: starting contact sync', [
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'client_id' => $client->id,
            'audience_tag' => $audienceTag,
        ]);

        $mergeFieldDefs = $this->fetchMergeFields();
        $billingAddress = $client->billingAddress;

        // Only include ADDRESS when the minimum required sub-fields are populated.
        // Mailchimp validates the address object even when the field is not required.
        $addressValue = null;
        if ($billingAddress
            && ! empty($billingAddress->address_line_1)
            && ! empty($billingAddress->city)
            && ! empty($billingAddress->postal_code)
            && ! empty($billingAddress->country_code)
        ) {
            $addressValue = [
                'addr1' => $billingAddress->address_line_1,
                'addr2' => $billingAddress->address_line_2 ?? '',
                'city' => $billingAddress->city,
                'state' => $billingAddress->state?->name ?? '',
                'zip' => $billingAddress->postal_code,
                'country' => $billingAddress->country_code,
            ];
        } else {
            Log::debug('Mailchimp: billing address incomplete or missing, ADDRESS field will be skipped', [
                'contact_id' => $contact->id,
                'has_billing_address' => $billingAddress !== null,
            ]);
        }

        $candidates = [
            'FNAME' => $contact->first_name ?? '',
            'LNAME' => $contact->last_name ?? '',
            'COMPANY' => $client->name ?? '',
            'ADDRESS' => $addressValue,
            'PHONE' => $contact->first_available_phone ?? '',
        ];

        $mergeFields = [];

        foreach ($mergeFieldDefs as $tag => $def) {
            if (! array_key_exists($tag, $candidates)) {
                continue;
            }

            $value = $candidates[$tag];

            if ($value === null || $value === '') {
                if ($def['required']) {
                    Log::warning('Mailchimp: required merge field has no value', [
                        'contact_id' => $contact->id,
                        'merge_field' => $tag,
                    ]);

                    throw new \RuntimeException("Required merge field {$tag} has no value.");
                }
                continue;
            }

            $mergeFields[$tag] = $value;
        }

        $payload = ['email_address' => $contact->email, 'status_if_new' => 'subscribed'];

        if (! empty($mergeFields)) {
            $payload['merge_fields'] = $mergeFields;
        }

        Log::debug('Mailchimp: upserting member', [
            'contact_id' => $contact->id,
            'merge_field_tags' => array_keys($mergeFields),
        ]);

        $this->http()
            ->put("{$this->baseUrl}/lists/{$this->contactsListId}/members/{$subscriberHash}", $payload)
            ->throw();

        Log::info('Mailchimp: member upserted successfully', [
            'contact_id' => $contact->id,
            'subscriber_hash' => $subscriberHash,
        ]);

        // Tags are managed via a separate endpoint — PUT /members does not accept a tags field
        $this->http()
            ->post("{$this->baseUrl}/lists/{$this->contactsListId}/members/{$subscriberHash}/tags", [
                'tags' => [['name' => $audienceTag, 'status' => 'active']],
            ])
            ->throw();

        Log::info('Mailchimp: audience tag applied', [
            'contact_id' => $contact->id,
            'tag' => $audienceTag,
        ]);

        $member = $this->fetchMember($subscriberHash) ?? [];

        Log::info('Mailchimp: contact sync completed', [
            'contact_id' => $contact->id,
            'mailchimp_id' => $member['id'] ?? null,
            'status' => $member['status'] ?? null,
        ]);

        return $member;
    }

    public function unsubscribeContact(string $email): void
    {
        $subscriberHash = md5(strtolower($email));

        $this->http()
            ->patch("{$this->baseUrl}/lists/{$this->contactsListId}/members/{$subscriberHash}", [
                'status' => 'unsubscribed',
            ])
            ->throw();
    }

    public function syncEmailTemplate(MailTemplate $template): void
    {
        $name = $template->name;
        $html = $template->getHtmlBodyForLocale();

        if (empty($html)) {
            return;
        }

        $existing = $this->findTemplateByName($name);

        if ($existing) {
            $this->http()
                ->patch("{$this->baseUrl}/templates/{$existing['id']}", [
                    'name' => $name,
                    'html' => $html,
                ])
                ->throw();
        } else {
            $this->http()
                ->post("{$this->baseUrl}/templates", [
                    'name' => $name,
                    'html' => $html,
                ])
                ->throw();
        }
    }

    /**
     * Fetch a member from Mailchimp by subscriber hash.
     *
     * @return array{email_address: string, status: string, id: string}|null
     */
    public function fetchMember(string $subscriberHash): ?array
    {
        try {
            $response = $this->http()
                ->get("{$this->baseUrl}/lists/{$this->contactsListId}/members/{$subscriberHash}")
                ->throw();

            return $response->json();
        } catch (RequestException $e) {
            Log::warning('Mailchimp: member fetch failed', [
                'subscriber_hash' => $subscriberHash,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch the configured contacts list to validate credentials and list ID.
     *
     * @return array{id: string, name: string}|null
     */
    public function fetchContactsList(): ?array
    {
        try {
            $response = $this->http()
                ->get("{$this->baseUrl}/lists/{$this->contactsListId}")
                ->throw();

            return $response->json();
        } catch (RequestException $e) {
            return null;
        }
    }

    /**
     * Fetch all available Mailchimp audience lists.
     *
     * @return array<int, array{id: string, name: string, stats: array}>
     */
    public function fetchAllLists(): array
    {
        $response = $this->http()
            ->get("{$this->baseUrl}/lists", ['count' => 100, 'fields' => 'lists.id,lists.name,lists.stats'])
            ->throw();

        return $response->json('lists', []);
    }

    public function useList(string $listId): void
    {
        $this->contactsListId = $listId;
        $this->mergeFieldTags = null; // reset cache when list changes
    }

    /**
     * Return merge field definitions for the configured list (cached per instance).
     * Keyed by tag, each entry has 'tag', 'required', 'type'.
     *
     * @return array<string, array{tag: string, required: bool, type: string}>
     */
    public function fetchMergeFields(): array
    {
        if ($this->mergeFieldTags !== null) {
            return $this->mergeFieldTags;
        }

        try {
            $response = $this->http()
                ->get("{$this->baseUrl}/lists/{$this->contactsListId}/merge-fields", [
                    'count' => 100,
                    'fields' => 'merge_fields.tag,merge_fields.required,merge_fields.type',
                ])
                ->throw();

            $this->mergeFieldTags = collect($response->json('merge_fields', []))
                ->keyBy('tag')
                ->all();
        } catch (RequestException $e) {
            Log::warning('Mailchimp: could not fetch merge fields', ['error' => $e->getMessage()]);
            $this->mergeFieldTags = [];
        }

        return $this->mergeFieldTags;
    }

    /** @deprecated Use fetchMergeFields() */
    public function fetchMergeFieldTags(): array
    {
        return array_keys($this->fetchMergeFields());
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->contactsListId);
    }

    public function hasApiKey(): bool
    {
        return ! empty($this->apiKey);
    }

    public function deleteTemplateByName(string $name): void
    {
        $template = $this->findTemplateByName($name);

        if (! $template) {
            return;
        }

        $this->http()
            ->delete("{$this->baseUrl}/templates/{$template['id']}")
            ->throw();
    }

    protected function findTemplateByName(string $name): ?array
    {
        try {
            $response = $this->http()
                ->get("{$this->baseUrl}/templates", ['count' => 1000])
                ->throw();

            $templates = $response->json('templates', []);

            foreach ($templates as $template) {
                if ($template['name'] === $name) {
                    return $template;
                }
            }
        } catch (RequestException $e) {
            Log::error('Mailchimp: failed to fetch templates', ['error' => $e->getMessage()]);
        }

        return null;
    }

    protected function http(): PendingRequest
    {
        return $this->client->withBasicAuth('anystring', $this->apiKey)
            ->acceptJson()
            ->asJson();
    }
}

<?php

namespace App\Models\Mail;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JeffersonGoncalves\LaravelMail\Models\MailTemplate as BaseMailTemplate;

class MailTemplate extends BaseMailTemplate
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    public function replaceVariablesForClient(?\App\Models\Common\Client $client): self
    {
        if (! $client) {
            return $this;
        }

        $locales = array_unique(array_merge(
            array_keys($this->getTranslations('subject') ?: []),
            array_keys($this->getTranslations('html_body') ?: []),
            array_keys($this->getTranslations('text_body') ?: []),
            ['en']
        ));

        foreach ($locales as $locale) {
            $subj = $this->getTranslation('subject', $locale);
            if ($subj) {
                $this->setTranslation('subject', $locale, self::replacePlaceholders($subj, $client));
            }

            $body = $this->getTranslation('html_body', $locale);
            if ($body) {
                $this->setTranslation('html_body', $locale, self::replacePlaceholders($body, $client));
            }

            $textBody = $this->getTranslation('text_body', $locale);
            if ($textBody) {
                $this->setTranslation('text_body', $locale, self::replacePlaceholders($textBody, $client));
            }
        }

        return $this;
    }

    public static function replacePlaceholders(string $text, \App\Models\Common\Client $client): string
    {
        $variables = [
            'name' => $client->name,
            'status' => $client->status instanceof \UnitEnum ? $client->status->value : $client->status,
            'nric' => $client->nric,
            'website' => $client->website,
            'account_number' => $client->account_number,
            'notes' => $client->notes,

            'contact.first_name' => $client->primaryContact?->first_name,
            'contact.last_name' => $client->primaryContact?->last_name,
            'contact.full_name' => $client->primaryContact?->fullName,
            'contact.email' => $client->primaryContact?->email,
            'contact.phone' => $client->primaryContact?->firstAvailablePhone,

            'billing_address.address_line_1' => $client->billingAddress?->address_line_1,
            'billing_address.address_line_2' => $client->billingAddress?->address_line_2,
            'billing_address.city' => $client->billingAddress?->city,
            'billing_address.state' => $client->billingAddress?->state?->name,
            'billing_address.postal_code' => $client->billingAddress?->postal_code,
            'billing_address.country' => $client->billingAddress?->country?->name,

            'shipping_address.address_line_1' => $client->shippingAddress?->address_line_1,
            'shipping_address.address_line_2' => $client->shippingAddress?->address_line_2,
            'shipping_address.city' => $client->shippingAddress?->city,
            'shipping_address.state' => $client->shippingAddress?->state?->name,
            'shipping_address.postal_code' => $client->shippingAddress?->postal_code,
            'shipping_address.country' => $client->shippingAddress?->country?->name,
        ];

        foreach ($variables as $key => $value) {
            $valStr = (string) $value;
            $patterns = [
                '{{lead.' . $key . '}}', '{{ lead.' . $key . ' }}', '{{lead.' . $key . ' }}', '{{ lead.' . $key . '}}',
                '{{client.' . $key . '}}', '{{ client.' . $key . ' }}', '{{client.' . $key . ' }}', '{{ client.' . $key . '}}',
            ];
            $text = str_replace($patterns, $valStr, $text);
        }

        return $text;
    }
}

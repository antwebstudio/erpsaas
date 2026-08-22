<?php

namespace App\Enums\Setting;

use Filament\Support\Contracts\HasLabel;

enum EmailAccountType: string implements HasLabel
{
    case DefaultAccount = 'default';
    case Marketing = 'marketing';

    public function getLabel(): ?string
    {
        $label = match ($this) {
            self::DefaultAccount => 'Default Account',
            self::Marketing => 'Marketing Account',
        };

        return translate($label);
    }

    public function description(): string
    {
        $description = match ($this) {
            self::DefaultAccount => 'Used for invoices, estimates, variation orders, and all other system emails.',
            self::Marketing => 'Used for marketing emails, template emails, and emails sent to leads.',
        };

        return translate($description);
    }
}

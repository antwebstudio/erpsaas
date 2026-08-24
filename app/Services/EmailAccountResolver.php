<?php

namespace App\Services;

use App\Enums\Setting\EmailAccountType;
use App\Models\Setting\EmailAccount;
use App\Scopes\CurrentCompanyScope;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailer as ConcreteMailer;

/**
 * Resolves which mailer to use when sending a company email.
 *
 * The marketing account is used for marketing emails, template emails,
 * and emails sent to leads. Every other outbound email (invoices,
 * estimates, variation orders, etc.) uses the default account.
 *
 * If a company hasn't configured the requested account (or the marketing
 * account specifically), sending falls back to the default account, and
 * finally to the application's globally configured mailer.
 */
class EmailAccountResolver
{
    public function mailer(EmailAccountType $type, ?int $companyId = null): Mailer
    {
        $companyId ??= Auth::user()?->current_company_id;

        $account = $companyId ? $this->resolveAccount($type, $companyId) : null;

        if (! $account) {
            return Mail::mailer(Config::get('mail.default'));
        }

        // Built fresh on every call (rather than registered under a stable
        // config key and cached via Mail::mailer()) so that a long-running
        // queue worker always picks up the latest saved credentials instead
        // of reusing a stale mailer/transport from before the account was
        // last edited.
        $config = $account->toMailerConfig();

        $mailer = Mail::build($config);

        if ($mailer instanceof ConcreteMailer && isset($config['from']['address'])) {
            $mailer->alwaysFrom($config['from']['address'], $config['from']['name'] ?? null);
        }

        return $mailer;
    }

    protected function resolveAccount(EmailAccountType $type, int $companyId): ?EmailAccount
    {
        $account = EmailAccount::withoutGlobalScope(CurrentCompanyScope::class)
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->first();

        if ($account && $account->isConfigured()) {
            return $account;
        }

        // Marketing emails fall back to the default account when no
        // marketing account has been configured for the company.
        if ($type === EmailAccountType::Marketing) {
            return $this->resolveAccount(EmailAccountType::DefaultAccount, $companyId);
        }

        return null;
    }
}

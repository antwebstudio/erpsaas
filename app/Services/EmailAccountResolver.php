<?php

namespace App\Services;

use App\Enums\Setting\EmailAccountType;
use App\Models\Setting\EmailAccount;
use App\Scopes\CurrentCompanyScope;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

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

        $mailerName = "email_account_{$account->id}";

        if (! Config::has("mail.mailers.{$mailerName}")) {
            Config::set("mail.mailers.{$mailerName}", $account->toMailerConfig());
        }

        return Mail::mailer($mailerName);
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

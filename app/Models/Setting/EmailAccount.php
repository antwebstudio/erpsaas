<?php

namespace App\Models\Setting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Setting\EmailAccountType;
use Illuminate\Database\Eloquent\Model;

class EmailAccount extends Model
{
    use Blamable;
    use CompanyOwned;

    protected $table = 'email_accounts';

    protected $fillable = [
        'company_id',
        'type',
        'mailer',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => EmailAccountType::class,
        'port' => 'integer',
        'password' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Whether this account has enough information to actually send mail.
     * SMTP-based mailers require a host; API-based mailers (ses, postmark,
     * resend, mailgun, sendmail) only need a from address.
     */
    public function isConfigured(): bool
    {
        if (! $this->is_active || blank($this->from_address)) {
            return false;
        }

        if ($this->mailer === 'smtp') {
            return filled($this->host);
        }

        return true;
    }

    /**
     * Build the ad-hoc mailer config array consumed by Laravel's MailManager,
     * e.g. config(["mail.mailers.{$name}" => $account->toMailerConfig()]).
     *
     * @return array<string, mixed>
     */
    public function toMailerConfig(): array
    {
        return array_filter([
            'transport' => $this->mailer,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'encryption' => $this->encryption,
            'from' => filled($this->from_address) ? [
                'address' => $this->from_address,
                'name' => $this->from_name,
            ] : null,
        ], static fn ($value) => ! is_null($value) && $value !== '');
    }
}

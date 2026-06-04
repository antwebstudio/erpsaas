<?php

namespace App\Models\Mail;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JeffersonGoncalves\LaravelMail\Models\MailLog as BaseMailLog;

class MailLog extends BaseMailLog
{
    protected static function booted(): void
    {
        static::creating(static function (MailLog $mailLog) {
            if (empty($mailLog->tenant_id)) {
                // 1. Try to get tenant_id from the associated MailTemplate
                if (! empty($mailLog->mail_template_id)) {
                    $template = \App\Models\Mail\MailTemplate::find($mailLog->mail_template_id);
                    if ($template && $template->tenant_id) {
                        $mailLog->tenant_id = $template->tenant_id;

                        return;
                    }
                }

                // 2. Try to get tenant_id from session or authenticated user
                $companyId = session('current_company_id');
                if (! $companyId && ($user = auth()->user())) {
                    $companyId = $user->current_company_id;
                }

                if ($companyId) {
                    $mailLog->tenant_id = $companyId;
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }
}

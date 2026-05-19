<?php

namespace App\Models\Mail;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JeffersonGoncalves\LaravelMail\Models\MailSuppression as BaseMailSuppression;

class MailSuppression extends BaseMailSuppression
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }
}

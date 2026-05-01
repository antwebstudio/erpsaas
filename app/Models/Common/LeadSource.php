<?php

namespace App\Models\Common;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'lead_source_id');
    }
}

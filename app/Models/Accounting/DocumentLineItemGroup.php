<?php

namespace App\Models\Accounting;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentLineItemGroup extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'document_line_item_groups';

    protected $fillable = [
        'company_id',
        'parent_id',
        'documentable_type',
        'documentable_id',
        'offering_category_id',
        'name',
        'order',
        'created_by',
        'updated_by',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentLineItem::class, 'group_id')->orderBy('line_number');
    }

    public function offeringCategory(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Common\OfferingCategory::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->orderBy('order');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }
}

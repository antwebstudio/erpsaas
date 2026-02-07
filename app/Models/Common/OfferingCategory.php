<?php

namespace App\Models\Common;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Models\Common\Offering;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

class OfferingCategory extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;
    
    use \Kalnoy\Nestedset\NodeTrait;
    use \Studio15\FilamentTree\Concerns\InteractsWithTree;

    public function save(array $options = [])
    {
        if (empty($this->company_id)) {
            $companyId = session('current_company_id');

            if (! $companyId && ($user = Auth::user()) && ($companyId = $user->current_company_id)) {
                session(['current_company_id' => $companyId]);
            }
            
            if ($companyId) {
                $this->company_id = $companyId;
            }
        }

        return parent::save($options);
    }

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    public function offerings(): BelongsToMany
    {
        return $this->belongsToMany(Offering::class, 'offering_offering_category', 'offering_category_id', 'offering_id');
    }

    public function documentLineItemGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Accounting\DocumentLineItemGroup::class);
    }

    public static function getTreeLabelAttribute(): string
    {
        return 'name';
    }

    protected function getScopeAttributes(): array
    {
        return ['company_id'];
    }

    /**
     * Override to allow withDepth() to work with scoped attributes when model instance is empty.
     */
    public function applyNestedSetScope($query, $table = null)
    {
        if (! $scoped = $this->getScopeAttributes()) {
            return $query;
        }

        if (! $table) {
            $table = $this->getTable();
        }

        foreach ($scoped as $attribute) {
            $value = $this->getAttributeValue($attribute);
            
            if ($value !== null) {
                $query->where($table.'.'.$attribute, '=', $value);
            } else {
                // If value covers null, we assume we are inside a subquery (like withDepth) 
                // and need to correlate with the main table
                $query->whereColumn($table.'.'.$attribute, '=', $this->getTable().'.'.$attribute);
            }
        }

        return $query;
    }
}

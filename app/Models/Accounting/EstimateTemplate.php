<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;

class EstimateTemplate extends Estimate
{
    protected $table = 'estimates';

    public function getMorphClass(): string
    {
        return (new Estimate)->getMorphClass();
    }

    protected static function booted(): void
    {
        static::addGlobalScope('is_template', function (Builder $builder) {
            $builder->isTemplate();
        });
    }

    protected static function booting(): void
    {
        static::creating(function ($model) {
            $model->is_template = true;
        });
    }
}

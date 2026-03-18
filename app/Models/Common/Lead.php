<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Builder;

class Lead extends Client
{
    public function getMorphClass(): string
    {
        return Client::class;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('type', function (Builder $builder) {
            $builder->where('type', 'lead');
        });
    }
}

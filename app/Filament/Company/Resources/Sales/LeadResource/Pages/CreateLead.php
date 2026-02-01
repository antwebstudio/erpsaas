<?php

namespace App\Filament\Company\Resources\Sales\LeadResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Filament\Company\Resources\Sales\LeadResource;
use App\Models\Common\Lead;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateLead extends CreateRecord
{
    use HandlePageRedirect;

    protected static string $resource = LeadResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['type'] = 'lead';
        return Lead::createWithRelations($data);
    }
}

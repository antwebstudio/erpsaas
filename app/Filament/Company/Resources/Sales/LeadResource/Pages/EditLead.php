<?php

namespace App\Filament\Company\Resources\Sales\LeadResource\Pages;

use App\Concerns\HandlePageRedirect;
use App\Filament\Company\Resources\Sales\LeadResource;
use App\Models\Common\Client;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class EditLead extends EditRecord
{
    use HandlePageRedirect;

    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Client $record */
        $record->updateWithRelations($data);

        return $record;
    }
}

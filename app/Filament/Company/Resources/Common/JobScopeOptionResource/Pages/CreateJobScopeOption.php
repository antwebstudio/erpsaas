<?php

namespace App\Filament\Company\Resources\Common\JobScopeOptionResource\Pages;

use App\Filament\Company\Resources\Common\JobScopeOptionResource;
use App\Models\Setting\CompanyDefault;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Enums\Common\OfferingType;

class CreateJobScopeOption extends CreateRecord
{
    protected static string $resource = JobScopeOptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $companyDefault = CompanyDefault::where('company_id', auth()->user()->current_company_id)->first();

        if (! $companyDefault || ! $companyDefault->income_account_id) {
            Notification::make()
                ->title('Configuration Error')
                ->body('Please set a Default Income Account in Settings > Quotation before creating Job Scope Options.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['income_account_id'] = $companyDefault->income_account_id;
        $data['type'] = OfferingType::Service;
        $data['sellable'] = true;
        $data['purchasable'] = false;

        return $data;
    }
}

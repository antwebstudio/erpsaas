<?php

namespace App\Filament\Company\Resources\Core\UserResource\Pages;

use App\Filament\Company\Resources\Core\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        /** @var \App\Models\User $user */
        $user = $this->record;

        $isAdvanceMode = $this->data['advance_mode'] ?? false;
        $selectedRole = $this->data['selected_role'] ?? null;

        if ($isAdvanceMode) {
            $user->companies()->syncWithoutDetaching([filament()->getTenant()->id]);
        } else {
            if (in_array(strtolower($selectedRole), ['admin', 'super admin'])) {
                // Already synced to all companies.
            } elseif (strtolower($selectedRole) === 'sales') {
                // Applied to system company only. Do NOT force-attach current tenant unless it matches system company.
                $systemCompanyId = config('erp.erp_system_company_id');
                if ($systemCompanyId && filament()->getTenant()->id == $systemCompanyId) {
                    $user->companies()->syncWithoutDetaching([filament()->getTenant()->id]);
                }
            } else {
                $user->companies()->syncWithoutDetaching([filament()->getTenant()->id]);
            }
        }

        // Optionally set as current company if not set
        if (! $user->current_company_id) {
            $user->current_company_id = filament()->getTenant()->id;
            $user->save();
        }
    }
}

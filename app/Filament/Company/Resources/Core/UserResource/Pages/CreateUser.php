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

        $user->companies()->syncWithoutDetaching([filament()->getTenant()->id]);

        // Optionally set as current company if not set
        if (! $user->current_company_id) {
            $user->current_company_id = filament()->getTenant()->id;
            $user->save();
        }
    }
}

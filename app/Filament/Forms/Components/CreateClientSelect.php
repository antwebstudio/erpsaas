<?php

namespace App\Filament\Forms\Components;

use App\Filament\Company\Resources\Sales\ClientResource;
use App\Models\Common\Client;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;

class CreateClientSelect extends Select
{
    protected bool $isRelationshipDisabled = false;

    protected static bool $nextIsDisabled = false;

    public static function make(?string $name = null, bool $disabledRelationship = false): static
    {
        static::$nextIsDisabled = $disabledRelationship;
        $static = parent::make($name);
        static::$nextIsDisabled = false;

        return $static;
    }

    protected function setUp(): void
    {
        $this->isRelationshipDisabled = static::$nextIsDisabled;

        parent::setUp();

        if ($this->isRelationshipDisabled) {
            return;
        }

        $this
            ->searchable()
            ->preload()
            ->createOptionForm(fn (Form $form) => $this->createClientForm($form))
            ->createOptionAction(fn (Action $action) => $this->createClientAction($action));

        $this->relationship('clientAndLead', 'name');

        $this->createOptionUsing(static function (array $data) {
            return DB::transaction(static function () use ($data) {
                $client = Client::createWithRelations($data);

                return $client->getKey();
            });
        });
    }

    protected function createClientForm(Form $form): Form
    {
        return ClientResource::form($form);
    }

    protected function createClientAction(Action $action): Action
    {
        return $action
            ->label('Create client')
            ->slideOver()
            ->modalWidth(MaxWidth::ThreeExtraLarge)
            ->modalHeading('Create a new client');
    }
}

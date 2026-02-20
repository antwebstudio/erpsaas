<?php

namespace App\Filament\Forms\Components;

use App\Filament\Company\Resources\Common\OfferingResource;
use App\Models\Common\Offering;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\Enums\MaxWidth;

class CreateOfferingSelect extends Select
{
    protected bool $isPurchasable = true;

    protected bool $isSellable = true;

    public bool $isRelationshipDisabled = false;

    protected static bool $nextIsDisabled = false;



    public static function make(?string $name = null, bool $disabledRelationship = false): static
    {
        static::$nextIsDisabled = $disabledRelationship;
        $static = parent::make($name);
        static::$nextIsDisabled = false;

        return $static;
    }

    public function disableRelationship(bool $condition = true): static
    {
        $this->isRelationshipDisabled = $condition;

        return $this;
    }

    public function isRelationshipDisabled(): bool
    {
        return $this->isRelationshipDisabled || static::$nextIsDisabled || config('app.disable_custom_select_relationships', false);
    }

    public function isPurchasable(): bool
    {
        return $this->isPurchasable;
    }

    public function isSellable(): bool
    {
        return $this->isSellable;
    }

    public function isSellableAndPurchasable(): bool
    {
        return $this->isSellable && $this->isPurchasable;
    }

    public function purchasable(bool $condition = true): static
    {
        $this->isPurchasable = $condition;
        $this->isSellable = false;

        return $this;
    }

    public function sellable(bool $condition = true): static
    {
        $this->isSellable = $condition;
        $this->isPurchasable = false;

        return $this;
    }

    protected function setUp(): void
    {
        $this->isRelationshipDisabled = static::$nextIsDisabled;
        
        parent::setUp();

        if ($this->isRelationshipDisabled()) {
            return;
        }

        $this
            ->searchable()
            ->createOptionForm(fn (Form $form) => $this->createOfferingForm($form))
            ->createOptionAction(fn (Action $action) => $this->createOfferingAction($action));

        $this->relationship(
            name: fn () => $this->isPurchasable() && ! $this->isSellable() ? 'purchasableOffering' : ($this->isSellable() && ! $this->isPurchasable() ? 'sellableOffering' : 'offering'),
            titleAttribute: 'name'
        );

        $this->createOptionUsing(function (array $data, Form $form) {
            if ($this->isSellableAndPurchasable()) {
                $attributes = array_flip($data['attributes'] ?? []);

                $data['sellable'] = isset($attributes['Sellable']);
                $data['purchasable'] = isset($attributes['Purchasable']);
            } else {
                $data['sellable'] = $this->isSellable;
                $data['purchasable'] = $this->isPurchasable;
            }

            unset($data['attributes']);

            $offering = Offering::create($data);

            $form->model($offering)->saveRelationships();

            return $offering->id;
        });
    }

    public function createOfferingForm(Form $form): Form
    {
        return OfferingResource::form($form);
    }

    protected function createOfferingAction(Action $action): Action
    {
        return $action
            ->modalHeading('Create new offering')
            ->modalWidth(MaxWidth::SixExtraLarge)
            ->slideOver();
    }
}

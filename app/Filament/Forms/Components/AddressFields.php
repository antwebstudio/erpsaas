<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

class AddressFields extends Grid
{
    protected bool $isSoftRequired = false;

    protected bool | Closure $isCountryDisabled = false;

    protected bool | Closure $isRequired = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            TextInput::make('address_line_1')
                ->label('Address line 1')
                ->required(fn () => $this->isRequired())
                ->live()
                ->maxLength(255),
            TextInput::make('address_line_2')
                ->label('Address line 2')
                ->live()
                ->maxLength(255),
            CountrySelect::make('country_code')
                ->disabled(fn () => $this->isCountryDisabled())
                ->clearStateField()
                ->required(fn () => $this->isRequired())
                ->live(),
            StateSelect::make('state_id')
                ->required(fn () => $this->isRequired())
                ->live(),
            TextInput::make('city')
                ->label('City')
                ->required(fn () => $this->isRequired())
                ->live()
                ->maxLength(255),
            TextInput::make('postal_code')
                ->label('Postal code')
                ->required(fn () => $this->isRequired())
                ->live()
                ->maxLength(255),
        ]);
    }

    public function softRequired(bool $condition = true): static
    {
        $this->setSoftRequired($condition);

        return $this;
    }

    protected function setSoftRequired(bool $condition): void
    {
        $this->isSoftRequired = $condition;

        $childComponents = $this->getChildComponents();

        foreach ($childComponents as $component) {
            if ($component instanceof Field && $component->isRequired()) {
                $component->markAsRequired(! $condition);
            }
        }
    }

    public function required(bool | Closure $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->isRequired);
    }

    public function requiredIfAnyFilled(): static
    {
        return $this->required(function (Get $get) {
            return collect([
                'address_line_1',
                'address_line_2',
                'country_code',
                'state_id',
                'city',
                'postal_code',
            ])->some(fn ($field) => filled($get($field)));
        });
    }

    public function disabledCountry(bool | Closure $condition = true): static
    {
        $this->isCountryDisabled = $condition;

        return $this;
    }

    public function isCountryDisabled(): bool
    {
        return $this->evaluate($this->isCountryDisabled);
    }
}

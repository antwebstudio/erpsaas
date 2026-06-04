<?php

namespace App\Filament\Forms\Components;

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\AdjustmentScope;
use App\Enums\Accounting\AdjustmentStatus;
use App\Enums\Accounting\AdjustmentType;
use App\Models\Accounting\Adjustment;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CreateAdjustmentSelect extends Select
{
    protected ?AdjustmentCategory $category = null;

    protected ?AdjustmentType $type = null;

    protected bool $includeInactive = false;

    protected string $adjustmentsRelationship = 'adjustments';

    protected bool $forceEnabled = false;

    public bool $isRelationshipDisabled = false;

    protected static bool $nextIsDisabled = false;

    public static function make(?string $name = null, bool $disabledRelationship = false): static
    {
        static::$nextIsDisabled = $disabledRelationship;
        $static = parent::make($name);
        static::$nextIsDisabled = false;

        return $static;
    }

    public function forceEnableRelationship(bool $condition = true): static
    {
        $this->forceEnabled = $condition;

        return $this;
    }

    public function disableRelationship(bool $condition = true): static
    {
        $this->isRelationshipDisabled = $condition;

        return $this;
    }

    public function isRelationshipDisabled(): bool
    {
        if ($this->forceEnabled) {
            return false;
        }

        return $this->isRelationshipDisabled || static::$nextIsDisabled || config('app.disable_custom_select_relationships', false);
    }

    public function getRelationshipName(): ?string
    {
        if ($this->isRelationshipDisabled()) {
            return null;
        }

        return $this->getAdjustmentsRelationship();
    }

    public function category(AdjustmentCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function type(AdjustmentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function includeInactive(bool $condition = true): static
    {
        $this->includeInactive = $condition;

        return $this;
    }

    public function adjustmentsRelationship(string $relationship): static
    {
        $this->adjustmentsRelationship = $relationship;

        return $this;
    }

    public function getCategory(): ?AdjustmentCategory
    {
        return $this->category;
    }

    public function getType(): ?AdjustmentType
    {
        return $this->type;
    }

    public function includesInactive(): bool
    {
        return $this->includeInactive;
    }

    public function getAdjustmentsRelationship(): string
    {
        return $this->adjustmentsRelationship;
    }

    protected function setUp(): void
    {
        $this->isRelationshipDisabled = static::$nextIsDisabled;

        parent::setUp();

        if ($this->isRelationshipDisabled || static::$nextIsDisabled) {
            return;
        }

        $this
            ->searchable()
            ->createOptionForm($this->createAdjustmentForm())
            ->createOptionAction(fn (Action $action) => $this->createAdjustmentAction($action));

        $this->relationship(
            name: $this->getAdjustmentsRelationship(),
            titleAttribute: 'name',
            modifyQueryUsing: function (Builder $query, ?Model $record) {
                $query->withoutGlobalScopes([\App\Scopes\CurrentCompanyScope::class]);

                if ($this->getCategory()) {
                    $query->where('category', $this->getCategory());
                }

                if ($this->getType()) {
                    $query->where('type', $this->getType());
                }

                if (! $this->includesInactive()) {
                    $relationshipName = $this->getAdjustmentsRelationship();
                    $existingAdjustmentIds = [];

                    if ($record && $record->exists) {
                        // Use already loaded relationship to prevent N+1 queries if possible
                        if ($record->relationLoaded($relationshipName)) {
                            $existingAdjustmentIds = $record->getRelation($relationshipName)->pluck('id')->toArray();
                        } else {
                            $existingAdjustmentIds = collect($record->{$relationshipName} ?? [])->pluck('id')->toArray();
                        }
                    }

                    $query->where(function (Builder $query) use ($existingAdjustmentIds) {
                        $query->where('status', AdjustmentStatus::Active);
                        if (! empty($existingAdjustmentIds)) {
                            $query->orWhereIn('adjustments.id', $existingAdjustmentIds);
                        }
                    });
                }
            }
        );

        $this->createOptionUsing(function (array $data) {
            $data['category'] = $this->getCategory();
            $data['type'] = $this->getType();

            return Adjustment::create($data)->id;
        });
    }

    public function createAdjustmentForm(): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Group::make([
                TextInput::make('rate')
                    ->numeric()
                    ->required()
                    ->rule('min:0')
                    ->default(0)
                    ->live(),
                Select::make('computation')
                    ->options(AdjustmentComputation::class)
                    ->required()
                    ->default(AdjustmentComputation::Percentage)
                    ->selectablePlaceholder(false)
                    ->live(),
            ])->columns(2),
            Group::make([
                Select::make('scope')
                    ->options(AdjustmentScope::class)
                    ->required()
                    ->default(AdjustmentScope::Product)
                    ->selectablePlaceholder(false),
                Checkbox::make('is_recoverable')
                    ->label('Recoverable')
                    ->default(false),
            ])->columns(2),
            Textarea::make('description')
                ->maxLength(65535),
            DateTimePicker::make('start_at')
                ->label('Start Date')
                ->default(now()),
            DateTimePicker::make('end_at')
                ->label('End Date'),
        ];
    }

    protected function createAdjustmentAction(Action $action): Action
    {
        return $action
            ->modalHeading('Create new adjustment')
            ->modalWidth(MaxWidth::Large)
            ->slideOver();
    }
}

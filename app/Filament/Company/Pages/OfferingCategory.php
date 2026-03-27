<?php

namespace App\Filament\Company\Pages;

use Kalnoy\Nestedset\QueryBuilder;
use Studio15\FilamentTree\Components\TreePage;
use Filament\Forms;

class OfferingCategory extends TreePage
{
    public static function getModel(): string|QueryBuilder
    {
        return \App\Models\Common\OfferingCategory::class;
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = \Illuminate\Support\Facades\Auth::user();
        
        return $user ? $user->can('view_any_common::offering::category') : false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        foreach ($items as $item) {
            $item->visible(static::shouldRegisterNavigation());
        }

        return $items;
    }

    public static function getCreateForm(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required(),
        ];
    }

    public static function getEditForm(): array
    {
        return [
            Forms\Components\TextInput::make('name')->required(),
        ];
    }

    public static function getInfolistColumns(): array
    {
        return [
            //
        ];
    }
}

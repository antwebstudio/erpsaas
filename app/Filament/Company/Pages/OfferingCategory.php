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

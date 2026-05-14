<?php

namespace App\Enums\Common;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ClientStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Archived = 'archived';
    case Completed = 'completed';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Active => 'success',
            self::Archived => 'gray',
            self::Completed => 'primary',
        };
    }
}

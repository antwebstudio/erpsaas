<?php

namespace App\Enums\Setting;

use App\Enums\Concerns\ParsesEnum;
use Filament\Support\Contracts\HasLabel;

enum TextAlign: string implements HasLabel
{
    use ParsesEnum;

    case Left = 'left';
    case Center = 'center';
    case Right = 'right';

    public const DEFAULT = self::Left->value;

    public function getLabel(): ?string
    {
        return translate($this->name);
    }
}

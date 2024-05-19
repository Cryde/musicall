<?php

namespace App\Enum\Publication;

use phpDocumentor\Reflection\Types\Self_;

enum PublicationType: int
{
    case Text = 1;
    case Video = 2;

    public function label(): string
    {
        return match ($this) {
            self::Text => 'text',
            self::Video => 'video'
        };
    }
}
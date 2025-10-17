<?php declare(strict_types=1);

namespace App\Enum\Publication;

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

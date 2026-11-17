<?php

declare(strict_types=1);

namespace App\Enum\Service;

/** What a sound engineer hands back (#1410). */
enum DeliveryFormat: string
{
    case Wav = 'wav';
    case Stems = 'stems';
    case Ddp = 'ddp';
    case Mp3 = 'mp3';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $format): string => $format->value, self::cases());
    }
}

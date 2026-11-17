<?php

declare(strict_types=1);

namespace App\Enum\Service;

enum ServicePlaceType: string
{
    case AtMyPlace = 'at_my_place';
    case AtClientPlace = 'at_client_place';
    case Remote = 'remote';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}

<?php

declare(strict_types=1);

namespace App\Enum\Service;

/** How the teacher search sorts (#1314). */
enum TeachingSearchOrder: string
{
    case Distance = 'distance';
    /** The cheapest lesson first, per 30 minutes; a teacher with no lesson price last. */
    case Price = 'price';
    /** The teachers whose trial lesson is free first. */
    case FreeTrial = 'free_trial';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $order): string => $order->value, self::cases());
    }
}

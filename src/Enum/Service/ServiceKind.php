<?php

declare(strict_types=1);

namespace App\Enum\Service;

/**
 * What a member offers (#683). Every per-kind rule is an exhaustive match here, so a new kind fails PHPStan until it
 * declares each of them.
 */
enum ServiceKind: string
{
    case Teaching = 'teaching';

    /** The type its views are counted under: teaching keeps the one its views were counted under as a teacher profile. */
    public function viewableType(): string
    {
        return match ($this) {
            self::Teaching => 'teacher_profile',
        };
    }
}

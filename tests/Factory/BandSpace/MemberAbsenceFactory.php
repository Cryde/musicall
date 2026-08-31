<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\MemberAbsence;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MemberAbsence>
 */
final class MemberAbsenceFactory extends PersistentObjectFactory
{
    /**
     * The dates are pinned rather than faked: they decide both the window a test asks for and the
     * order rows come back in, and faker defaults have flipped sorts in CI before.
     */
    protected function defaults(): array
    {
        return [
            'member' => BandSpaceMembershipFactory::new(),
            'startDate' => new DateTimeImmutable('2026-06-10'),
            'endDate' => new DateTimeImmutable('2026-06-12'),
            'reason' => null,
        ];
    }

    public static function class(): string
    {
        return MemberAbsence::class;
    }
}

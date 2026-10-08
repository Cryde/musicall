<?php

declare(strict_types=1);

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\AgendaEntryAvailability;
use App\Enum\BandSpace\AvailabilityAnswer;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<AgendaEntryAvailability>
 */
final class AgendaEntryAvailabilityFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'agendaEntry' => AgendaEntryFactory::new(),
            'membership' => BandSpaceMembershipFactory::new(),
            'occurrenceDate' => new \DateTimeImmutable('+7 days'),
            'answer' => AvailabilityAnswer::Yes,
            'answeredAt' => new \DateTimeImmutable('2026-01-01 10:00:00'),
        ];
    }

    public static function class(): string
    {
        return AgendaEntryAvailability::class;
    }
}

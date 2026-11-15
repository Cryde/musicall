<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Service;

use App\Entity\Service\Service;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\ServiceKind;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\StudentLevel;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<Service>
 */
final class ServiceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'description' => self::faker()->paragraphs(2, true),
            'yearsOfExperience' => self::faker()->numberBetween(1, 30),
        ];
    }

    public function teaching(): self
    {
        return $this->with(function (): array {
            $details = new TeachingDetails();
            $details->studentLevels = self::faker()->randomElements(
                array_map(static fn (StudentLevel $level): string => $level->value, StudentLevel::cases()),
                self::faker()->numberBetween(1, 3),
            );
            $details->ageGroups = self::faker()->randomElements(
                array_map(static fn (AgeGroup $group): string => $group->value, AgeGroup::cases()),
                self::faker()->numberBetween(1, 4),
            );
            $details->offersTrial = self::faker()->boolean(60);
            $details->trialPrice = self::faker()->optional(0.5)->randomElement([0, 0, 500, 1000, 1500]);

            return [
                'kind' => ServiceKind::Teaching,
                'headline' => self::faker()->optional(0.7)->sentence(4),
                'details' => $details->toArray(),
            ];
        });
    }

    public static function class(): string
    {
        return Service::class;
    }
}

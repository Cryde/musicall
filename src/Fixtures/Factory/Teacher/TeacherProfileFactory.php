<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Teacher;

use App\Entity\Teacher\TeacherProfile;
use App\Enum\Teacher\AgeGroup;
use App\Enum\Teacher\StudentLevel;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<TeacherProfile>
 */
final class TeacherProfileFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        $studentLevels = self::faker()->randomElements(
            array_map(fn(StudentLevel $level) => $level->value, StudentLevel::cases()),
            self::faker()->numberBetween(1, 3)
        );

        $ageGroups = self::faker()->randomElements(
            array_map(fn(AgeGroup $group) => $group->value, AgeGroup::cases()),
            self::faker()->numberBetween(1, 4)
        );

        return [
            'creationDatetime' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'description' => self::faker()->paragraphs(2, true),
            'yearsOfExperience' => self::faker()->numberBetween(1, 30),
            'studentLevels' => $studentLevels,
            'ageGroups' => $ageGroups,
            'courseTitle' => self::faker()->optional(0.7)->sentence(4),
            'offersTrial' => self::faker()->boolean(60),
            'trialPrice' => self::faker()->optional(0.5)->randomElement([0, 0, 500, 1000, 1500]),
        ];
    }

    public static function class(): string
    {
        return TeacherProfile::class;
    }
}

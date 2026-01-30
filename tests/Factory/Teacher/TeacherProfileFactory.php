<?php

declare(strict_types=1);

namespace App\Tests\Factory\Teacher;

use App\Entity\Teacher\TeacherProfile;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<TeacherProfile>
 */
final class TeacherProfileFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'creationDatetime' => new DateTimeImmutable('2024-01-15T10:00:00+00:00'),
        ];
    }

    public function withDescription(string $description): self
    {
        return $this->with(['description' => $description]);
    }

    public function withYearsOfExperience(int $years): self
    {
        return $this->with(['yearsOfExperience' => $years]);
    }

    public function withStudentLevels(array $levels): self
    {
        return $this->with(['studentLevels' => $levels]);
    }

    public function withAgeGroups(array $groups): self
    {
        return $this->with(['ageGroups' => $groups]);
    }

    public function withTrial(int $priceInCents): self
    {
        return $this->with([
            'offersTrial' => true,
            'trialPrice' => $priceInCents,
        ]);
    }

    public static function class(): string
    {
        return TeacherProfile::class;
    }
}

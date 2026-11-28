<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator\Service;

use App\Service\Service\YearsOfExperience;
use App\Validator\Service\ExperienceStartYear;
use App\Validator\Service\ExperienceStartYearValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ExperienceStartYearValidatorTest extends TestCase
{
    /** @return iterable<string, array{int|null}> */
    public static function acceptedYears(): iterable
    {
        yield 'left out' => [null];
        yield 'started this year' => [2026];
        yield 'exactly 70 years back' => [1956];
    }

    #[DataProvider('acceptedYears')]
    public function test_a_year_in_the_last_70_years_is_accepted(?int $year): void
    {
        $this->assertCount(0, $this->validatorIn2026()->validate($year, new ExperienceStartYear()));
    }

    /** @return iterable<string, array{int}> */
    public static function refusedYears(): iterable
    {
        yield 'next year' => [2027];
        yield 'more than 70 years back' => [1955];
    }

    #[DataProvider('refusedYears')]
    public function test_a_future_year_or_one_more_than_70_years_back_is_refused(int $year): void
    {
        $violations = $this->validatorIn2026()->validate($year, new ExperienceStartYear());

        $this->assertSame([[
            'message' => 'service.experience_start_year_invalid',
            'parameters' => ['{{ value }}' => (string) $year, '{{ min }}' => '1956', '{{ max }}' => '2026'],
            'code' => Range::NOT_IN_RANGE_ERROR,
        ]], array_map(static fn (ConstraintViolationInterface $violation): array => [
            'message' => $violation->getMessageTemplate(),
            'parameters' => $violation->getParameters(),
            'code' => $violation->getCode(),
        ], iterator_to_array($violations)));
    }

    private function validatorIn2026(): ValidatorInterface
    {
        $validator = new ExperienceStartYearValidator(new YearsOfExperience(new MockClock('2026-06-15 10:00:00')));

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([ExperienceStartYearValidator::class => $validator]))
            ->getValidator();
    }
}

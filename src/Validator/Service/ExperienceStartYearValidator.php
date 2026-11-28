<?php

declare(strict_types=1);

namespace App\Validator\Service;

use App\Service\Service\YearsOfExperience;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/** A Range whose bounds follow the clock, so they are built at validation time rather than in the metadata. */
class ExperienceStartYearValidator extends ConstraintValidator
{
    public function __construct(private readonly YearsOfExperience $yearsOfExperience)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExperienceStartYear) {
            throw new UnexpectedTypeException($constraint, ExperienceStartYear::class);
        }

        $this->context->getValidator()->inContext($this->context)->validate($value, new Range(
            notInRangeMessage: $constraint->message,
            min: $this->yearsOfExperience->startYear(YearsOfExperience::MAX),
            max: $this->yearsOfExperience->currentYear(),
        ));
    }
}

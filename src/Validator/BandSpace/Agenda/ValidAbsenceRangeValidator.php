<?php

declare(strict_types=1);

namespace App\Validator\BandSpace\Agenda;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidAbsenceRangeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidAbsenceRange) {
            throw new UnexpectedTypeException($constraint, ValidAbsenceRange::class);
        }

        if ($value === null) {
            return;
        }

        $start = $this->readDate($value, 'startDate');
        $end = $this->readDate($value, 'endDate');

        // A date that is missing or malformed has its own NotBlank or Assert\Date violation. The
        // order and the span of a pair that is not a pair are not questions worth answering, and a
        // second violation on the same mistake only confuses the form.
        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            return;
        }

        if ($end < $start) {
            $this->context->buildViolation($constraint->endBeforeStartMessage)
                ->atPath('endDate')
                ->setCode(ValidAbsenceRange::END_BEFORE_START_CODE)
                ->addViolation();

            return;
        }

        // Inclusive on both ends, so a single day counts as one.
        $days = (int) $start->diff($end)->days + 1;
        if ($days > ValidAbsenceRange::MAX_DAYS) {
            $this->context->buildViolation($constraint->rangeTooLongMessage)
                ->atPath('endDate')
                ->setCode(ValidAbsenceRange::RANGE_TOO_LONG_CODE)
                ->addViolation();
        }
    }

    /**
     * The day the property names, or null when it is absent or is not a bare `Y-m-d`.
     *
     * `isset()` rather than a bare read, because a property the caller omitted is uninitialized.
     */
    private function readDate(object $value, string $property): ?DateTimeImmutable
    {
        if (!property_exists($value, $property) || !isset($value->{$property})) {
            return null;
        }

        return CalendarDay::parse($value->{$property});
    }
}

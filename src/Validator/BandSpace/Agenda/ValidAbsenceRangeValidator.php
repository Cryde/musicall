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

        $start = $this->parseDate($value, 'startDate');
        $end = $this->parseDate($value, 'endDate');

        // Anything the property constraints already rejected - missing, not a date, out of order -
        // has its own violation. Saying "too long" on top of it would be noise, and the span of a
        // pair that does not parse is not a question worth answering.
        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable || $end < $start) {
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
     * The property as a real date, or null when it is absent or not a `Y-m-d` calendar day.
     *
     * isset() rather than a bare read: an omitted date leaves the typed property uninitialized on
     * the create DTO, and touching it would fatal before NotBlank ever runs. The format is checked
     * by round-trip so `2026-02-31`, which DateTimeImmutable would happily read as 3 March, is not
     * mistaken for a span this constraint can measure.
     */
    private function parseDate(object $value, string $property): ?DateTimeImmutable
    {
        $raw = property_exists($value, $property) && isset($value->{$property}) ? $value->{$property} : null;
        if (!is_string($raw)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return $date !== false && $date->format('Y-m-d') === $raw ? $date : null;
    }
}

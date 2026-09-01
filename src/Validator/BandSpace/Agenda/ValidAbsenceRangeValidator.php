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

        // A missing date has its own NotNull violation, and an out of order pair its own
        // GreaterThanOrEqual one. Adding "too long" on top would be noise, and the span of a pair
        // that is not a pair is not a question worth answering.
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
     * The serializer has already parsed and rejected anything malformed, so this only has to cope
     * with the field being absent. isset() rather than a bare read, because a property the caller
     * omitted may be uninitialized rather than null.
     */
    private function readDate(object $value, string $property): ?DateTimeImmutable
    {
        if (!property_exists($value, $property) || !isset($value->{$property})) {
            return null;
        }

        $date = $value->{$property};

        return $date instanceof DateTimeImmutable ? $date : null;
    }
}

<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceRecurrenceCreate;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class RecurrenceEndDateValidator extends ConstraintValidator
{
    public const string ERROR_CODE_BEFORE_START = 'music_all_cdf98d05-ab4a-435a-b2ad-904ed3815cf3';
    public const string ERROR_CODE_MAX_DURATION = 'music_all_f1f407d3-4e94-4d6b-a4f0-c26d867f0084';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RecurrenceEndDate) {
            throw new UnexpectedTypeException($constraint, RecurrenceEndDate::class);
        }

        if (!$value instanceof FinanceRecurrenceCreate) {
            throw new UnexpectedValueException($value, FinanceRecurrenceCreate::class);
        }

        if (!isset($value->startDate, $value->endDate)) {
            return;
        }

        $start = new \DateTimeImmutable($value->startDate);
        $end = new \DateTimeImmutable($value->endDate);

        if ($end <= $start) {
            $this->context->buildViolation($constraint->messageBeforeStart)
                ->atPath('endDate')
                ->setCode(self::ERROR_CODE_BEFORE_START)
                ->addViolation();

            return;
        }

        $maxEnd = $start->modify('+' . RecurrenceEndDate::MAX_YEARS . ' years');
        if ($end > $maxEnd) {
            $this->context->buildViolation($constraint->messageMaxDuration)
                ->setParameter('{{ limit }}', (string) RecurrenceEndDate::MAX_YEARS)
                ->atPath('endDate')
                ->setCode(self::ERROR_CODE_MAX_DURATION)
                ->addViolation();
        }
    }
}

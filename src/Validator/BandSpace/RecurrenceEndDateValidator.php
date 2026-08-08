<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Finance\FinanceRecurrenceCreate;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class RecurrenceEndDateValidator extends ConstraintValidator
{
    public const string ERROR_CODE_BEFORE_START = 'music_all_cdf98d05-ab4a-435a-b2ad-904ed3815cf3';
    public const string ERROR_CODE_MAX_DURATION = 'music_all_f1f407d3-4e94-4d6b-a4f0-c26d867f0084';
    public const string ERROR_CODE_INVALID_FORMAT = 'music_all_0a54d1e2-4e0f-4cf5-9c2b-2ba2f3d4a6b1';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RecurrenceEndDate) {
            throw new UnexpectedTypeException($constraint, RecurrenceEndDate::class);
        }

        // A PATCH is validated on FinanceRecurrenceResource, whose start date comes from the stored
        // recurrence, so the end-after-start rule and the duration cap apply to an edit as they do to a
        // creation instead of being reachable only through the create endpoint.
        if (!$value instanceof FinanceRecurrenceCreate && !$value instanceof FinanceRecurrenceResource) {
            throw new UnexpectedValueException($value, FinanceRecurrenceCreate::class);
        }

        if (!isset($value->startDate, $value->endDate)) {
            return;
        }

        $start = date_create_immutable($value->startDate);
        $end = date_create_immutable($value->endDate);

        // A PATCH has no per-property date format constraint to lean on, so an unparsable date has to be
        // reported here rather than blow up further down the write.
        if ($start === false || $end === false) {
            $this->context->buildViolation($constraint->messageInvalidFormat)
                ->atPath('endDate')
                ->setCode(self::ERROR_CODE_INVALID_FORMAT)
                ->addViolation();

            return;
        }

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

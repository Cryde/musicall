<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FinanceAmountRangeValidator extends ConstraintValidator
{
    public const string ERROR_CODE_EXCLUSIVE = 'music_all_434bf45e-e038-4c98-be5d-d48d96d6e2c3';
    public const string ERROR_CODE_MIN_MAX = 'music_all_340f2feb-4d58-4e44-bd3c-e0ddda45a31a';
    public const string ERROR_CODE_INCOMPLETE = 'music_all_6b1c0a5f-8f1e-4a1e-9a2c-2c9e1f7a4d80';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof FinanceAmountRange) {
            throw new UnexpectedTypeException($constraint, FinanceAmountRange::class);
        }

        if (!is_object($value) || !property_exists($value, 'amountMin') || !property_exists($value, 'amountMax')) {
            return;
        }

        $hasAmount = property_exists($value, 'amount') && $value->amount !== null;
        $hasRange = $value->amountMin !== null || $value->amountMax !== null;

        if ($hasAmount && $hasRange) {
            $this->context->buildViolation($constraint->exclusiveMessage)
                ->atPath('amount')
                ->setCode(self::ERROR_CODE_EXCLUSIVE)
                ->addViolation();

            return;
        }

        // One bound on its own is the shape that silently costs the band money: every total reads the
        // entry through COALESCE(amount, ROUND((min + max) / 2), ...) and an addition with a NULL side
        // is NULL, so a half filled estimate used to count as zero everywhere without a single warning.
        if ($value->amountMin === null || $value->amountMax === null) {
            if ($hasRange) {
                $this->context->buildViolation($constraint->incompleteMessage)
                    ->atPath($value->amountMin === null ? 'amountMin' : 'amountMax')
                    ->setCode(self::ERROR_CODE_INCOMPLETE)
                    ->addViolation();
            }

            return;
        }

        if ($value->amountMin > $value->amountMax) {
            $this->context->buildViolation($constraint->message)
                ->atPath('amountMin')
                ->setCode(self::ERROR_CODE_MIN_MAX)
                ->addViolation();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Validator\Teacher;

use App\ApiResource\Teacher\Private\TeacherProfileInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidLevelGoalsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidLevelGoals) {
            throw new UnexpectedTypeException($constraint, ValidLevelGoals::class);
        }
        // Absent from the request: the stored goals stay, nothing to check (#1312, the app does not send them).
        if (!$value instanceof TeacherProfileInput || !is_array($value->levelGoals)) {
            return;
        }

        $seen = [];
        foreach ($value->levelGoals as $index => $goal) {
            if ($goal->level === null) {
                continue;
            }
            $path = sprintf('levelGoals[%d].level', $index);
            if (!in_array($goal->level, $value->studentLevels, true)) {
                $this->context->buildViolation($constraint->notAcceptedMessage)
                    ->atPath($path)
                    ->setCode(ValidLevelGoals::ERROR_CODE_NOT_ACCEPTED)
                    ->addViolation();
            } elseif (isset($seen[$goal->level])) {
                $this->context->buildViolation($constraint->duplicateMessage)
                    ->atPath($path)
                    ->setCode(ValidLevelGoals::ERROR_CODE_DUPLICATE)
                    ->addViolation();
            }
            $seen[$goal->level] = true;
        }
    }
}

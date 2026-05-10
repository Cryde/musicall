<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Task\TaskReorder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TaskReorderPositionsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TaskReorderPositions) {
            throw new UnexpectedTypeException($constraint, TaskReorderPositions::class);
        }

        if (!$value instanceof TaskReorder) {
            return;
        }

        if ($value->positions === []) {
            $this->context->buildViolation($constraint->emptyMessage)
                ->atPath('positions')
                ->setCode(TaskReorderPositions::ERROR_CODE)
                ->addViolation();

            return;
        }

        foreach ($value->positions as $index => $item) {
            $valid = is_array($item)
                && array_key_exists('id', $item)
                && array_key_exists('position', $item)
                && is_string($item['id'])
                && is_int($item['position']);

            if (!$valid) {
                $this->context->buildViolation($constraint->invalidItemMessage)
                    ->atPath("positions[$index]")
                    ->setCode(TaskReorderPositions::ERROR_CODE)
                    ->addViolation();
            }
        }
    }
}

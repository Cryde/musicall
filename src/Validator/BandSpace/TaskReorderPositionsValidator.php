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

        $shapeOk = true;
        foreach ($value->positions as $index => $item) {
            $valid = is_array($item)
                && array_key_exists('id', $item)
                && array_key_exists('position', $item)
                && is_string($item['id'])
                && is_int($item['position']);

            if (!$valid) {
                $shapeOk = false;
                $this->context->buildViolation($constraint->invalidItemMessage)
                    ->atPath("positions[$index]")
                    ->setCode(TaskReorderPositions::ERROR_CODE)
                    ->addViolation();
            }
        }

        if (!$shapeOk) {
            return;
        }

        // A duplicate would pass the column-membership check in the processor, which compares
        // sets, and then leave one of the positions it named unused.
        $ids = array_column($value->positions, 'id');
        if (count(array_unique($ids)) !== count($ids)) {
            $this->context->buildViolation($constraint->duplicateIdMessage)
                ->atPath('positions')
                ->setCode(TaskReorderPositions::ERROR_CODE)
                ->addViolation();

            return;
        }

        $positionValues = array_column($value->positions, 'position');
        $expected = range(0, count($positionValues) - 1);
        sort($positionValues);
        if ($positionValues !== $expected) {
            $this->context->buildViolation($constraint->notContiguousMessage)
                ->atPath('positions')
                ->setCode(TaskReorderPositions::ERROR_CODE)
                ->addViolation();
        }
    }
}

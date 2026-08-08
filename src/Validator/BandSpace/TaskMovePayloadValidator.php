<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\ApiResource\BandSpace\Task\TaskMove;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TaskMovePayloadValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TaskMovePayload) {
            throw new UnexpectedTypeException($constraint, TaskMovePayload::class);
        }

        if (!$value instanceof TaskMove) {
            return;
        }

        // No ordering at all means "append to the destination column", which the server resolves.
        if ($value->positions === []) {
            return;
        }

        $allItemsValid = true;
        foreach ($value->positions as $index => $item) {
            $valid = is_array($item)
                && array_key_exists('id', $item)
                && array_key_exists('position', $item)
                && is_string($item['id'])
                && is_int($item['position']);

            if (!$valid) {
                $this->context->buildViolation($constraint->invalidItemMessage)
                    ->atPath("positions[$index]")
                    ->setCode(TaskMovePayload::ERROR_CODE)
                    ->addViolation();
                $allItemsValid = false;
            }
        }

        if (!$allItemsValid) {
            return;
        }

        $positionIds = array_column($value->positions, 'id');

        // A duplicate would pass the column-membership check, which compares sets, and then leave
        // one of the positions the payload named unused.
        if (count(array_unique($positionIds)) !== count($positionIds)) {
            $this->context->buildViolation($constraint->duplicateIdMessage)
                ->atPath('positions')
                ->setCode(TaskMovePayload::ERROR_CODE)
                ->addViolation();

            return;
        }

        $positionValues = array_column($value->positions, 'position');
        $expected = range(0, count($positionValues) - 1);
        sort($positionValues);
        if ($positionValues !== $expected) {
            $this->context->buildViolation($constraint->notContiguousMessage)
                ->atPath('positions')
                ->setCode(TaskMovePayload::ERROR_CODE)
                ->addViolation();

            return;
        }

        if (!in_array($value->taskId, $positionIds, true)) {
            $this->context->buildViolation($constraint->taskNotInPositionsMessage)
                ->atPath('task_id')
                ->setCode(TaskMovePayload::ERROR_CODE)
                ->addViolation();
        }
    }
}

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

        if ($value->positions === []) {
            $this->context->buildViolation($constraint->emptyMessage)
                ->atPath('positions')
                ->setCode(TaskMovePayload::ERROR_CODE)
                ->addViolation();

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
        if (!in_array($value->taskId, $positionIds, true)) {
            $this->context->buildViolation($constraint->taskNotInPositionsMessage)
                ->atPath('task_id')
                ->setCode(TaskMovePayload::ERROR_CODE)
                ->addViolation();
        }
    }
}

<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderSectionReorder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TechRiderSectionPositionsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TechRiderSectionPositions) {
            throw new UnexpectedTypeException($constraint, TechRiderSectionPositions::class);
        }

        if (!$value instanceof TechRiderSectionReorder) {
            return;
        }

        if ($value->positions === []) {
            $this->context->buildViolation($constraint->emptyMessage)
                ->atPath('positions')
                ->setCode(TechRiderSectionPositions::ERROR_CODE)
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
                    ->setCode(TechRiderSectionPositions::ERROR_CODE)
                    ->addViolation();
            }
        }

        if (!$shapeOk) {
            return;
        }

        // Uniqueness has to be checked here, on the raw list. The processor keys the payload
        // by id to apply it, which silently collapses a duplicate, and its membership check
        // then compares that collapsed set against the rider's sections and finds it
        // complete. So [{A,0},{B,1},{A,2}] would pass both halves of the rule and leave no
        // section holding position 0.
        $ids = array_column($value->positions, 'id');
        if (count(array_unique($ids)) !== count($ids)) {
            $this->context->buildViolation($constraint->duplicateIdMessage)
                ->atPath('positions')
                ->setCode(TechRiderSectionPositions::ERROR_CODE)
                ->addViolation();

            return;
        }

        $positionValues = array_column($value->positions, 'position');
        $expected = range(0, count($positionValues) - 1);
        sort($positionValues);
        if ($positionValues !== $expected) {
            $this->context->buildViolation($constraint->notContiguousMessage)
                ->atPath('positions')
                ->setCode(TechRiderSectionPositions::ERROR_CODE)
                ->addViolation();
        }
    }
}

<?php declare(strict_types=1);

namespace App\Validator\BandSpace\Setlist;

use App\ApiResource\BandSpace\Setlist\SetlistReorder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SetlistReorderPositionsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SetlistReorderPositions) {
            throw new UnexpectedTypeException($constraint, SetlistReorderPositions::class);
        }

        if (!$value instanceof SetlistReorder) {
            return;
        }

        if ($value->positions === []) {
            $this->context->buildViolation($constraint->emptyMessage)
                ->atPath('positions')
                ->setCode(SetlistReorderPositions::ERROR_CODE)
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
                    ->setCode(SetlistReorderPositions::ERROR_CODE)
                    ->addViolation();
            }
        }

        if (!$shapeOk) {
            return;
        }

        $positionValues = array_column($value->positions, 'position');
        $expected = range(0, count($positionValues) - 1);
        sort($positionValues);
        if ($positionValues !== $expected) {
            $this->context->buildViolation($constraint->notContiguousMessage)
                ->atPath('positions')
                ->setCode(SetlistReorderPositions::ERROR_CODE)
                ->addViolation();
        }
    }
}

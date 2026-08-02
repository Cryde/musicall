<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TechRiderSectionContentValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TechRiderSectionContent) {
            throw new UnexpectedTypeException($constraint, TechRiderSectionContent::class);
        }

        // Null is a section that has not been written in yet, which is how seeded ones start.
        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            $this->context->buildViolation($constraint->invalidMessage)
                ->setCode(TechRiderSectionContent::ERROR_CODE)
                ->addViolation();

            return;
        }

        // Encoding fails on a document nested past the JSON depth limit, which is also the
        // cheapest way to catch one: measuring depth by hand would mean walking it twice.
        $encoded = json_encode($value, flags: JSON_UNESCAPED_UNICODE, depth: TechRiderSectionContent::MAX_DEPTH);
        if ($encoded === false) {
            $this->context->buildViolation($constraint->tooDeepMessage)
                ->setCode(TechRiderSectionContent::ERROR_CODE)
                ->addViolation();

            return;
        }

        if (strlen($encoded) > TechRiderSectionContent::MAX_CONTENT_BYTES) {
            $this->context->buildViolation($constraint->tooLargeMessage)
                ->setParameter('{{ limit }}', (string) intdiv(TechRiderSectionContent::MAX_CONTENT_BYTES, 1000))
                ->setCode(TechRiderSectionContent::ERROR_CODE)
                ->addViolation();
        }
    }
}

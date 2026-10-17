<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderItemCreate;
use App\ApiResource\BandSpace\TechRider\TechRiderItemResource;
use App\Enum\BandSpace\RichTextSurface;
use App\Enum\BandSpace\TechRiderItemType;
use App\Service\BandSpace\RichText\TipTapDocumentChecker;
use App\Service\BandSpace\RichText\TipTapDocumentProblem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TechRiderItemContentValidator extends ConstraintValidator
{
    public function __construct(private readonly TipTapDocumentChecker $documentChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TechRiderItemContent) {
            throw new UnexpectedTypeException($constraint, TechRiderItemContent::class);
        }

        // Null is an item that has not been written in yet, which is how seeded ones start.
        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            $this->context->buildViolation($constraint->invalidMessage)
                ->setCode(TechRiderItemContent::ERROR_CODE)
                ->addViolation();

            return;
        }

        // Encoding fails on a document nested past the JSON depth limit, which is also the
        // cheapest way to catch one: measuring depth by hand would mean walking it twice.
        $encoded = json_encode($value, flags: JSON_UNESCAPED_UNICODE, depth: TechRiderItemContent::MAX_DEPTH);
        if ($encoded === false) {
            $this->context->buildViolation($constraint->tooDeepMessage)
                ->setCode(TechRiderItemContent::ERROR_CODE)
                ->addViolation();

            return;
        }

        if (strlen($encoded) > TechRiderItemContent::MAX_CONTENT_BYTES) {
            $this->context->buildViolation($constraint->tooLargeMessage)
                ->setParameter('{{ limit }}', (string) intdiv(TechRiderItemContent::MAX_CONTENT_BYTES, 1000))
                ->setCode(TechRiderItemContent::ERROR_CODE)
                ->addViolation();

            return;
        }

        // Only a text item holds prose; a contacts item keeps its presentation choices here.
        if (!$this->isTextItem($this->context->getObject())) {
            return;
        }

        $problem = $this->documentChecker->findProblem($value, RichTextSurface::TechRider);
        if ($problem instanceof TipTapDocumentProblem) {
            $this->context->buildViolation($problem->messageKey)
                ->setParameters($problem->parameters)
                ->setCode(TechRiderItemContent::ERROR_CODE)
                ->addViolation();
        }
    }

    private function isTextItem(mixed $item): bool
    {
        return ($item instanceof TechRiderItemCreate || $item instanceof TechRiderItemResource)
            && $item->type === TechRiderItemType::Text->value;
    }
}

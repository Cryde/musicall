<?php declare(strict_types=1);

namespace App\Validator\BandSpace;

use App\Service\BandSpace\RichText\TipTapDocumentChecker;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoteContentValidator extends ConstraintValidator
{
    public function __construct(private readonly TipTapDocumentChecker $documentChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoteContent) {
            throw new UnexpectedTypeException($constraint, NoteContent::class);
        }

        // Null is a note nobody has written in yet.
        if (!is_array($value)) {
            return;
        }

        if (strlen((string) json_encode($value, JSON_UNESCAPED_UNICODE)) > NoteContent::MAX_CONTENT_BYTES) {
            $this->context->buildViolation($constraint->tooLargeMessage)
                ->setParameter('{{ limit }}', (string) intdiv(NoteContent::MAX_CONTENT_BYTES, 1000))
                ->setCode(NoteContent::ERROR_CODE)
                ->addViolation();

            return;
        }

        $problem = $this->documentChecker->findProblem($value, allowsImages: true, allowsColour: false);
        if ($problem !== null) {
            $this->context->buildViolation($problem)
                ->setCode(NoteContent::ERROR_CODE)
                ->addViolation();
        }
    }
}

<?php declare(strict_types=1);

namespace App\Validator\BandSpace\Setlist;

use App\ApiResource\BandSpace\Setlist\SetlistItemCreate;
use App\Enum\BandSpace\SetlistItemType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidSetlistItemPayloadValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSetlistItemPayload) {
            throw new UnexpectedTypeException($constraint, ValidSetlistItemPayload::class);
        }

        if (!$value instanceof SetlistItemCreate) {
            return;
        }

        // Type is required by Assert\NotNull on the property - skip combo checks if absent.
        if ($value->type === null) {
            return;
        }

        $isSong = $value->type === SetlistItemType::Song;
        $labelTrimmed = $value->label !== null ? trim($value->label) : '';
        $hasLabel = $labelTrimmed !== '';
        $hasSongId = $value->songId !== null && trim($value->songId) !== '';

        if ($isSong) {
            if (!$hasSongId) {
                $this->context->buildViolation($constraint->songIdRequiredMessage)
                    ->atPath('song_id')
                    ->setCode(ValidSetlistItemPayload::ERROR_CODE)
                    ->addViolation();
            }
            if ($hasLabel) {
                $this->context->buildViolation($constraint->labelForbiddenMessage)
                    ->atPath('label')
                    ->setCode(ValidSetlistItemPayload::ERROR_CODE)
                    ->addViolation();
            }

            return;
        }

        // Non-song types: interlude, break, talk
        if (!$hasLabel) {
            $this->context->buildViolation($constraint->labelRequiredMessage)
                ->atPath('label')
                ->setCode(ValidSetlistItemPayload::ERROR_CODE)
                ->addViolation();
        }
        if ($hasSongId) {
            $this->context->buildViolation($constraint->songIdForbiddenMessage)
                ->atPath('song_id')
                ->setCode(ValidSetlistItemPayload::ERROR_CODE)
                ->addViolation();
        }
    }
}

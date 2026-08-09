<?php declare(strict_types=1);

namespace App\Validator\BandSpace\Setlist;

use App\ApiResource\BandSpace\Setlist\SetlistItemCreate;
use App\ApiResource\BandSpace\Setlist\SetlistItemResource;
use App\Enum\BandSpace\SetlistItemType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Guards the type/song/label combination on both write paths.
 *
 * Creation is the richer one, because it is the only place song_id is settable. Update carries no
 * song_id at all (type and song are immutable once the item exists), so only the label rule can
 * still be broken there, and it is the one that matters: emptying the label of a pause or an MC
 * slot leaves a row that renders as a bare dash in the editor, in live mode and on the printed
 * sheet, with no way back other than delete and recreate.
 */
class ValidSetlistItemPayloadValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSetlistItemPayload) {
            throw new UnexpectedTypeException($constraint, ValidSetlistItemPayload::class);
        }

        if ($value instanceof SetlistItemCreate) {
            // Type is required by Assert\NotNull on the property - skip combo checks if absent.
            if ($value->type === null) {
                return;
            }

            $this->validateLabel($value->type, $value->label, $constraint);
            $this->validateSongId($value->type, $value->songId, $constraint);

            return;
        }

        if ($value instanceof SetlistItemResource) {
            // Hydrated by the provider before the patch is applied, so the type is always there.
            $this->validateLabel($value->type, $value->label, $constraint);
        }
    }

    private function validateLabel(SetlistItemType $type, ?string $label, ValidSetlistItemPayload $constraint): void
    {
        $hasLabel = $this->isFilled($label);

        if ($type === SetlistItemType::Song) {
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
    }

    private function validateSongId(SetlistItemType $type, ?string $songId, ValidSetlistItemPayload $constraint): void
    {
        $hasSongId = $this->isFilled($songId);

        if ($type === SetlistItemType::Song) {
            if (!$hasSongId) {
                $this->context->buildViolation($constraint->songIdRequiredMessage)
                    ->atPath('song_id')
                    ->setCode(ValidSetlistItemPayload::ERROR_CODE)
                    ->addViolation();
            }

            return;
        }

        if ($hasSongId) {
            $this->context->buildViolation($constraint->songIdForbiddenMessage)
                ->atPath('song_id')
                ->setCode(ValidSetlistItemPayload::ERROR_CODE)
                ->addViolation();
        }
    }

    private function isFilled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}

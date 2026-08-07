<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderStagePlotInput;
use App\Enum\BandSpace\TechRiderColour;
use App\Enum\BandSpace\TechRiderStagePlotIcon;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class TechRiderStagePlotValidator extends ConstraintValidator
{
    private const array DOCUMENT_KEYS = ['version', 'stage', 'elements', 'legend'];
    private const array ELEMENT_KEYS = ['id', 'icon', 'x', 'y', 'scale', 'rotation', 'label', 'colour'];
    private const array LEGEND_KEYS = ['icon', 'label'];
    private const array STAGE_KEYS = ['aspect_ratio'];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TechRiderStagePlot) {
            throw new UnexpectedTypeException($constraint, TechRiderStagePlot::class);
        }

        if (!$value instanceof TechRiderStagePlotInput) {
            return;
        }

        // Null clears the plot, which is how an item goes back to empty without being deleted.
        $plot = $value->plot;
        if ($plot === null) {
            return;
        }

        if (!$this->isMap($plot)) {
            $this->violation($constraint->invalidDocumentMessage, 'plot', $constraint)->addViolation();

            return;
        }

        if (!$this->rejectUnknownKeys($plot, self::DOCUMENT_KEYS, 'plot', $constraint)) {
            return;
        }

        // Refused rather than defaulted. A document with no version is one whose shape nobody can
        // vouch for, and accepting it would put exactly that in the column.
        if (($plot['version'] ?? null) !== TechRiderStagePlot::SCHEMA_VERSION) {
            $this->violation($constraint->wrongVersionMessage, 'plot.version', $constraint)->addViolation();

            return;
        }

        $this->validateStage($plot['stage'] ?? null, $constraint);
        $this->validateElements($plot['elements'] ?? null, $constraint);
        $this->validateLegend($plot['legend'] ?? null, $constraint);
    }

    private function validateStage(mixed $stage, TechRiderStagePlot $constraint): void
    {
        if ($stage === null) {
            return;
        }

        if (!$this->isMap($stage)) {
            $this->violation($constraint->invalidEntryMessage, 'plot.stage', $constraint)->addViolation();

            return;
        }

        if (!$this->rejectUnknownKeys($stage, self::STAGE_KEYS, 'plot.stage', $constraint)) {
            return;
        }

        if (!array_key_exists('aspect_ratio', $stage)) {
            return;
        }

        $ratio = $this->toFloat($stage['aspect_ratio']);
        if ($ratio === null
            || $ratio < TechRiderStagePlot::MIN_ASPECT_RATIO
            || $ratio > TechRiderStagePlot::MAX_ASPECT_RATIO
        ) {
            $this->violation($constraint->aspectRatioOutOfRangeMessage, 'plot.stage.aspect_ratio', $constraint)
                ->setParameter('{{ min }}', (string) TechRiderStagePlot::MIN_ASPECT_RATIO)
                ->setParameter('{{ max }}', (string) TechRiderStagePlot::MAX_ASPECT_RATIO)
                ->addViolation();
        }
    }

    private function validateElements(mixed $elements, TechRiderStagePlot $constraint): void
    {
        if ($elements === null) {
            return;
        }

        if (!$this->isList($elements)) {
            $this->violation($constraint->invalidListMessage, 'plot.elements', $constraint)->addViolation();

            return;
        }

        if (count($elements) > TechRiderStagePlot::MAX_ELEMENTS) {
            $this->violation($constraint->tooManyElementsMessage, 'plot.elements', $constraint)
                ->setParameter('{{ limit }}', (string) TechRiderStagePlot::MAX_ELEMENTS)
                ->addViolation();

            return;
        }

        foreach ($elements as $index => $element) {
            $this->validateElement($element, "plot.elements[$index]", $constraint);
        }

        // Checked across the list, not per element, so it cannot be expressed on a single one.
        // An editor keying its canvas by id, or an export correlating elements with a legend,
        // would silently drop one of a duplicated pair.
        $ids = array_filter(
            array_map(static fn (mixed $element): mixed => is_array($element) ? ($element['id'] ?? null) : null, $elements),
            static fn (mixed $id): bool => is_string($id),
        );
        if (count(array_unique($ids)) !== count($ids)) {
            $this->violation($constraint->duplicateIdMessage, 'plot.elements', $constraint)->addViolation();
        }
    }

    private function validateElement(mixed $element, string $path, TechRiderStagePlot $constraint): void
    {
        if (!$this->isMap($element)) {
            $this->violation($constraint->invalidEntryMessage, $path, $constraint)->addViolation();

            return;
        }

        if (!$this->rejectUnknownKeys($element, self::ELEMENT_KEYS, $path, $constraint)) {
            return;
        }

        $id = $element['id'] ?? null;
        if (!$this->isNonEmptyString($id)) {
            $this->violation($constraint->invalidEntryMessage, "$path.id", $constraint)->addViolation();
        } elseif (mb_strlen($id) > TechRiderStagePlot::MAX_ELEMENT_ID_LENGTH) {
            $this->violation($constraint->tooLongIdMessage, "$path.id", $constraint)
                ->setParameter('{{ limit }}', (string) TechRiderStagePlot::MAX_ELEMENT_ID_LENGTH)
                ->addViolation();
        }

        $this->validateIcon($element['icon'] ?? null, "$path.icon", $constraint);

        foreach (['x', 'y'] as $axis) {
            $coordinate = $this->toFloat($element[$axis] ?? null);
            if ($coordinate === null || $coordinate < 0.0 || $coordinate > 1.0) {
                $this->violation($constraint->coordinateOutOfRangeMessage, "$path.$axis", $constraint)->addViolation();
            }
        }

        if (array_key_exists('scale', $element)) {
            $scale = $this->toFloat($element['scale']);
            if ($scale === null
                || $scale < TechRiderStagePlot::MIN_SCALE
                || $scale > TechRiderStagePlot::MAX_SCALE
            ) {
                $this->violation($constraint->scaleOutOfRangeMessage, "$path.scale", $constraint)
                    ->setParameter('{{ min }}', (string) TechRiderStagePlot::MIN_SCALE)
                    ->setParameter('{{ max }}', (string) TechRiderStagePlot::MAX_SCALE)
                    ->addViolation();
            }
        }

        // Deliberately not through toFloat(), unlike every other numeric rule here: an integer keeps
        // one representation per angle, so 90 and 90.0 cannot both be stored for the same rotation
        // and two plots that draw identically compare identically. Nothing normalises this
        // afterwards either, because the document is written back verbatim.
        if (array_key_exists('rotation', $element)
            && (!is_int($element['rotation'])
                || $element['rotation'] < TechRiderStagePlot::MIN_ROTATION
                || $element['rotation'] > TechRiderStagePlot::MAX_ROTATION)
        ) {
            $this->violation($constraint->invalidRotationMessage, "$path.rotation", $constraint)->addViolation();
        }

        $this->validateLabel($element['label'] ?? null, "$path.label", $constraint);
        $this->validateColour($element['colour'] ?? null, "$path.colour", $constraint);
    }

    private function validateLegend(mixed $legend, TechRiderStagePlot $constraint): void
    {
        if ($legend === null) {
            return;
        }

        if (!$this->isList($legend)) {
            $this->violation($constraint->invalidListMessage, 'plot.legend', $constraint)->addViolation();

            return;
        }

        if (count($legend) > TechRiderStagePlot::MAX_LEGEND_ENTRIES) {
            $this->violation($constraint->tooManyLegendEntriesMessage, 'plot.legend', $constraint)
                ->setParameter('{{ limit }}', (string) TechRiderStagePlot::MAX_LEGEND_ENTRIES)
                ->addViolation();

            return;
        }

        foreach ($legend as $index => $entry) {
            $path = "plot.legend[$index]";
            if (!$this->isMap($entry)) {
                $this->violation($constraint->invalidEntryMessage, $path, $constraint)->addViolation();

                continue;
            }

            if (!$this->rejectUnknownKeys($entry, self::LEGEND_KEYS, $path, $constraint)) {
                continue;
            }

            $this->validateIcon($entry['icon'] ?? null, "$path.icon", $constraint);
            $this->validateLabel($entry['label'] ?? null, "$path.label", $constraint);
        }
    }

    private function validateIcon(mixed $icon, string $path, TechRiderStagePlot $constraint): void
    {
        if (!is_string($icon) || TechRiderStagePlotIcon::tryFrom($icon) === null) {
            $this->violation($constraint->unknownIconMessage, $path, $constraint)->addViolation();
        }
    }

    private function validateLabel(mixed $label, string $path, TechRiderStagePlot $constraint): void
    {
        if ($label === null) {
            return;
        }

        if (!is_string($label)) {
            $this->violation($constraint->invalidEntryMessage, $path, $constraint)->addViolation();

            return;
        }

        // Characters, not bytes: a label reading « Ampli côté jardin » must not be measured with
        // its accents costing double.
        if (mb_strlen($label) > TechRiderStagePlot::MAX_LABEL_LENGTH) {
            $this->violation($constraint->tooLongLabelMessage, $path, $constraint)
                ->setParameter('{{ limit }}', (string) TechRiderStagePlot::MAX_LABEL_LENGTH)
                ->addViolation();
        }
    }

    private function validateColour(mixed $colour, string $path, TechRiderStagePlot $constraint): void
    {
        if ($colour === null) {
            return;
        }

        if (!is_string($colour) || TechRiderColour::tryFrom($colour) === null) {
            $this->violation($constraint->unknownColourMessage, $path, $constraint)->addViolation();
        }
    }

    /**
     * @param array<array-key, mixed> $subject
     * @param list<string> $allowed
     * @return bool false when a key was refused, so the caller stops rather than reporting the
     *              same entry twice
     */
    private function rejectUnknownKeys(
        array $subject,
        array $allowed,
        string $path,
        TechRiderStagePlot $constraint,
    ): bool {
        $unknown = array_diff(array_keys($subject), $allowed);
        if ($unknown === []) {
            return true;
        }

        $this->violation($constraint->unknownKeyMessage, $path, $constraint)->addViolation();

        return false;
    }

    /**
     * A JSON object, as opposed to a JSON array. json_decode turns both into PHP arrays, so this
     * is the only way to tell `{}` from `[]` once the payload is decoded.
     *
     * An empty array satisfies both this and isList, because `{}` and `[]` decode identically and
     * neither reading is wrong when there is nothing inside.
     */
    private function isMap(mixed $value): bool
    {
        return is_array($value) && (($value === []) || !array_is_list($value));
    }

    /** A JSON array. Empty counts: a plot with no elements yet is a normal document. */
    private function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    /** Ints are accepted for floats, because JSON writes 1 rather than 1.0. */
    private function toFloat(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function violation(
        string $message,
        string $path,
        TechRiderStagePlot $constraint,
    ): ConstraintViolationBuilderInterface {
        return $this->context->buildViolation($message)
            ->atPath($path)
            ->setCode(TechRiderStagePlot::ERROR_CODE);
    }
}

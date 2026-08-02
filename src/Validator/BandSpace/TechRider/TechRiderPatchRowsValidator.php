<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use App\ApiResource\BandSpace\TechRider\TechRiderPatchList;
use App\Enum\BandSpace\TechRiderColour;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class TechRiderPatchRowsValidator extends ConstraintValidator
{
    /**
     * Anything else in a row is refused rather than ignored. A client sending `mic` instead of
     * `microphone` would otherwise get a 204 and a silently empty column, and one sending back
     * the `id` or `position` it read would believe it controls values that a full replace
     * regenerates.
     */
    private const array ALLOWED_FIELDS = ['channel', 'name', 'microphone', 'routing', 'colour'];

    private const array TEXT_FIELD_LIMITS = [
        'name' => TechRiderPatchRows::MAX_NAME_LENGTH,
        'microphone' => TechRiderPatchRows::MAX_MICROPHONE_LENGTH,
        'routing' => TechRiderPatchRows::MAX_ROUTING_LENGTH,
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TechRiderPatchRows) {
            throw new UnexpectedTypeException($constraint, TechRiderPatchRows::class);
        }

        if (!$value instanceof TechRiderPatchList) {
            return;
        }

        // Empty arrays are a legitimate save: it is how a band clears a list it no longer needs.
        $this->validateDirection($value->inputs, 'inputs', $constraint);
        $this->validateDirection($value->outputs, 'outputs', $constraint);
    }

    /**
     * @param array<array-key, mixed> $rows
     */
    private function validateDirection(array $rows, string $path, TechRiderPatchRows $constraint): void
    {
        if (count($rows) > TechRiderPatchRows::MAX_ROWS_PER_DIRECTION) {
            $this->addViolation($constraint->tooManyRowsMessage, $path, $constraint)
                ->setParameter('{{ limit }}', (string) TechRiderPatchRows::MAX_ROWS_PER_DIRECTION)
                ->addViolation();

            // No point reporting the contents of a list that is refused wholesale.
            return;
        }

        $channelsUsable = true;
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !array_key_exists('channel', $row) || !is_int($row['channel'])) {
                $this->addViolation($constraint->invalidRowMessage, "{$path}[{$index}]", $constraint)->addViolation();
                $channelsUsable = false;

                continue;
            }

            $unknown = array_diff(array_keys($row), self::ALLOWED_FIELDS);
            if ($unknown !== []) {
                $this->addViolation($constraint->invalidRowMessage, "{$path}[{$index}]", $constraint)->addViolation();
                $channelsUsable = false;

                continue;
            }

            if ($row['channel'] < TechRiderPatchRows::MIN_CHANNEL || $row['channel'] > TechRiderPatchRows::MAX_CHANNEL) {
                $this->addViolation($constraint->channelOutOfRangeMessage, "{$path}[{$index}].channel", $constraint)
                    ->setParameter('{{ min }}', (string) TechRiderPatchRows::MIN_CHANNEL)
                    ->setParameter('{{ max }}', (string) TechRiderPatchRows::MAX_CHANNEL)
                    ->addViolation();
                // Still comparable for uniqueness, so the duplicate check keeps running.
            }

            $this->validateTextFields($row, "{$path}[{$index}]", $constraint);
            $this->validateColour($row, "{$path}[{$index}]", $constraint);
        }

        if (!$channelsUsable) {
            return;
        }

        // Reported per direction: the same number in inputs and outputs is normal, a bass DI on
        // input 10 and a wedge on output 10 are different things.
        /** @var list<int> $channels */
        $channels = array_column($rows, 'channel');
        $duplicates = array_keys(array_filter(
            array_count_values($channels),
            static fn (int $occurrences): bool => $occurrences > 1,
        ));

        if ($duplicates !== []) {
            $this->addViolation($constraint->duplicateChannelMessage, $path, $constraint)
                ->setParameter('{{ channel }}', (string) $duplicates[0])
                ->addViolation();
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function validateTextFields(array $row, string $rowPath, TechRiderPatchRows $constraint): void
    {
        foreach (self::TEXT_FIELD_LIMITS as $field => $limit) {
            if (!array_key_exists($field, $row) || $row[$field] === null) {
                continue;
            }

            if (!is_string($row[$field])) {
                $this->addViolation($constraint->invalidTextMessage, "{$rowPath}.{$field}", $constraint)->addViolation();

                continue;
            }

            // mb_strlen, not strlen: a routing note reading « Retour côté batterie » must be
            // measured in characters or its accents would each cost two of the limit.
            if (mb_strlen($row[$field]) > $limit) {
                $this->addViolation($constraint->tooLongMessage, "{$rowPath}.{$field}", $constraint)
                    ->setParameter('{{ limit }}', (string) $limit)
                    ->addViolation();
            }
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function validateColour(array $row, string $rowPath, TechRiderPatchRows $constraint): void
    {
        if (!array_key_exists('colour', $row) || $row['colour'] === null) {
            return;
        }

        if (!is_string($row['colour']) || TechRiderColour::tryFrom($row['colour']) === null) {
            $this->addViolation($constraint->unknownColourMessage, "{$rowPath}.colour", $constraint)->addViolation();
        }
    }

    private function addViolation(
        string $message,
        string $path,
        TechRiderPatchRows $constraint,
    ): \Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface {
        return $this->context->buildViolation($message)
            ->atPath($path)
            ->setCode(TechRiderPatchRows::ERROR_CODE);
    }
}

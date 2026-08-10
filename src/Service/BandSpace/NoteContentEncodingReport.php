<?php declare(strict_types=1);

namespace App\Service\BandSpace;

/**
 * What NoteContentEncodingInspector concluded about one note body.
 *
 * Three outcomes, and only one of them writes: a body that carries nothing the old read path could
 * have produced is clean, a body the inspector can attribute to that path is repairable, and a body
 * that carries entities it cannot attribute is handed back for a human to look at. The reason of a
 * review is text rather than a code because it is written for the operator reading the command
 * output, and each one names a different unprovable case.
 *
 * A repairable body is not one grade of evidence. Every rewritten text carries the strength of what
 * attributes it, and isInferred() answers the only question the operator has to ask before writing:
 * does any part of this repair rest on a sibling node rather than on the text in front of them.
 */
final readonly class NoteContentEncodingReport
{
    /**
     * @param array<string, mixed>|null $repairedContent the body as it should read, null unless repairable
     * @param list<array{before: string, after: string, inferred: bool}> $changes every text the repair rewrites
     */
    private function __construct(
        public ?array $repairedContent,
        public ?string $reviewReason,
        public array $changes,
    ) {
    }

    public static function clean(): self
    {
        return new self(null, null, []);
    }

    /**
     * @param array<string, mixed> $repairedContent
     * @param list<array{before: string, after: string, inferred: bool}> $changes
     */
    public static function repairable(array $repairedContent, array $changes): self
    {
        return new self($repairedContent, null, $changes);
    }

    public static function forReview(string $reason): self
    {
        return new self(null, $reason, []);
    }

    public function isRepairable(): bool
    {
        return $this->repairedContent !== null;
    }

    public function needsReview(): bool
    {
        return $this->reviewReason !== null;
    }

    /**
     * Whether the repair rewrites at least one text that nothing in itself attributes to the old
     * read path. Read off the changes rather than stored, so there is one source of truth and no
     * way for the flag and the list under it to disagree.
     */
    public function isInferred(): bool
    {
        foreach ($this->changes as $change) {
            if ($change['inferred']) {
                return true;
            }
        }

        return false;
    }
}

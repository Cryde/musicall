<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

use App\Service\BandSpace\RichText\TipTapVocabulary;

/** Where a rich text lives, which decides the extras its format allows on top of the shared vocabulary. */
enum RichTextSurface
{
    case Note;
    case TechRider;

    /** @return list<string> every node type besides `doc` */
    public function nodes(): array
    {
        return match ($this) {
            self::Note => [...TipTapVocabulary::NODES, 'text', TipTapVocabulary::IMAGE_NODE, ...TipTapVocabulary::CHECKLIST_NODES],
            self::TechRider => [...TipTapVocabulary::NODES, 'text'],
        };
    }

    /** @return list<string> */
    public function marks(): array
    {
        return match ($this) {
            self::Note => TipTapVocabulary::MARKS,
            self::TechRider => [...TipTapVocabulary::MARKS, TipTapVocabulary::COLOUR_MARK],
        };
    }

    /** @return list<int> */
    public function headingLevels(): array
    {
        return match ($this) {
            self::Note => TipTapVocabulary::NOTE_HEADING_LEVELS,
            self::TechRider => TipTapVocabulary::RIDER_HEADING_LEVELS,
        };
    }
}

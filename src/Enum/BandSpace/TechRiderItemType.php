<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * What one block of a rider holds, and therefore how it is edited and rendered.
 *
 * A rider is a set of authored items plus an ordered composition of them, rather than a
 * fixed set of pages, because the order is the band's editorial decision and the export has
 * to follow it. It also lets a rider carry two stage plots (a club setup and a festival one)
 * or two patch lists (electric and acoustic), which fixed pages could not express.
 *
 * Only implemented types appear here. Contacts, StagePlot and PatchList each arrive with
 * their own issue; adding a case before its renderer exists would put a type in the picker
 * that produces a blank block.
 */
enum TechRiderItemType: string
{
    case Text = 'text';
    case Document = 'document';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texte libre',
            self::Document => 'Document',
        };
    }
}

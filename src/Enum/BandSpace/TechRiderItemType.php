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
 * Only implemented types appear here. Contacts and StagePlot each arrive with their own issue;
 * adding a case before its renderer exists would put a type in the picker that produces a
 * blank block.
 */
enum TechRiderItemType: string
{
    case Text = 'text';
    case Document = 'document';
    case PatchList = 'patch_list';

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
            self::PatchList => 'Patch list',
        };
    }

    /**
     * Whether the item's body lives in its `content` JSON column. StagePlot joins Text here
     * when #769 lands.
     *
     * A predicate rather than a comparison at each call site: which type may hold what is one
     * rule, and a processor asking `type !== Text` would have to be found and edited again for
     * every type added.
     */
    public function storesContent(): bool
    {
        return $this === self::Text;
    }

    /** Whether the item's body is a file of the band space rather than something authored here. */
    public function usesFile(): bool
    {
        return $this === self::Document;
    }

    /** Whether the item's body lives in its own table, written through a dedicated endpoint. */
    public function storesRelationalRows(): bool
    {
        return $this === self::PatchList;
    }
}

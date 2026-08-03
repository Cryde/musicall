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
 * Every type in the model exists and every one has an editor, so the picker offers all of them.
 * A case is only added once something can render it: a type in the picker with no renderer would
 * produce a block that displays nothing.
 */
enum TechRiderItemType: string
{
    case Text = 'text';
    case Document = 'document';
    case PatchList = 'patch_list';
    case Contacts = 'contacts';
    case StagePlot = 'stage_plot';

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
            self::Contacts => 'Membres et contacts',
            self::StagePlot => 'Plan de scène',
        };
    }

    /**
     * Whether a client may write this item's `content` through the generic item endpoints.
     *
     * Not the same question as "does the body live in the content column", and conflating the two
     * is a hole: StagePlot's body *is* the content column, but it has a dedicated PUT with a
     * structural validator behind it. Answering yes here would let the generic PATCH store a plot
     * with an unknown icon and off-stage coordinates, because that endpoint only checks the
     * column's size and depth. The strict validator has to be the only door in.
     *
     * A predicate rather than a comparison at each call site: which type may do what is one rule,
     * and a processor asking `type !== Text` would have to be found and edited again for every
     * type added.
     */
    public function acceptsGenericContentWrite(): bool
    {
        // Text is prose and Contacts is a pair of presentation choices; neither has a dedicated
        // write path, so the generic one is how they are saved.
        return $this === self::Text || $this === self::Contacts;
    }

    /**
     * Why a generic content write was refused. Only meaningful when acceptsGenericContentWrite()
     * is false, and it lives here so the two processors that refuse cannot word it differently.
     */
    public function genericContentWriteRefusal(): string
    {
        return match ($this) {
            self::StagePlot => 'Un plan de scène se modifie via son endpoint dédié',
            default => 'Ce type d\'élément ne stocke pas de contenu rédigé',
        };
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

    /**
     * Whether the item is rendered from data the band already holds rather than authored in place.
     * A contacts item has no stored member list: it reads the roster afresh every time, so it
     * cannot go stale when somebody joins or leaves.
     */
    public function rendersFromBandData(): bool
    {
        return $this === self::Contacts;
    }
}

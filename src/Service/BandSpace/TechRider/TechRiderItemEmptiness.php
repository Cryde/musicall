<?php declare(strict_types=1);

namespace App\Service\BandSpace\TechRider;

use App\Entity\BandSpace\TechRiderItem;
use App\Enum\BandSpace\TechRiderItemType;
use App\Enum\BandSpace\TechRiderStagePlotIcon;

/**
 * Whether an item has anything to print (#1090). An empty item is left out of the PDF rather than
 * printed as a heading over a placeholder, and the editor marks it, so the rule lives here once for
 * both.
 */
readonly class TechRiderItemEmptiness
{
    public function __construct(
        private TechRiderContactsRenderer $contactsRenderer,
    ) {
    }

    /**
     * @param list<string>|null $contactLines the roster lines when the caller has already rendered
     *                                        them, so a contacts item does not query the roster twice
     */
    public function isEmpty(TechRiderItem $item, ?array $contactLines = null): bool
    {
        return match ($item->type) {
            TechRiderItemType::Text => !self::hasText($item->content),
            TechRiderItemType::StagePlot => !self::hasDrawableElement($item->content),
            TechRiderItemType::PatchList => $item->patchRows->isEmpty(),
            // A file moved to the trash is not empty: the PDF still names it, with a warning, so the
            // band sees the reference is broken instead of a page silently missing.
            TechRiderItemType::Document => $item->file === null,
            // The members are read from the roster, so the item is empty only with nobody in the band
            // and no note: in practice never, since a space always has its creator.
            TechRiderItemType::Contacts => !self::hasText($item->content['note'] ?? null)
                && ($contactLines ?? $this->contactsRenderer->render($item->techRider->bandSpace, false)['lines']) === [],
        };
    }

    /**
     * What the PDF can draw: an element with an icon it knows. Anything else prints nothing.
     *
     * @param array<string, mixed>|null $plot
     */
    private static function hasDrawableElement(?array $plot): bool
    {
        foreach ($plot['elements'] ?? [] as $element) {
            if (is_array($element) && is_string($element['icon'] ?? null) && TechRiderStagePlotIcon::tryFrom($element['icon']) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * A TipTap document counts as written once one of its text nodes holds more than whitespace. A
     * table of blank cells or a run of empty paragraphs is still empty.
     */
    private static function hasText(mixed $node): bool
    {
        if (!is_array($node)) {
            return false;
        }

        if (($node['type'] ?? null) === 'text' && is_string($node['text'] ?? null) && trim($node['text']) !== '') {
            return true;
        }

        foreach ($node['content'] ?? [] as $child) {
            if (self::hasText($child)) {
                return true;
            }
        }

        return false;
    }
}

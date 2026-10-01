<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * The columns of a patch list, in order, as both the editor and the PDF name them (#1089). They used
 * to disagree: the PDF printed « Destination / Type » for a column the editor called « Micro », so a
 * band filled in one thing and the venue read another.
 *
 * The same five columns serve both directions, because the rows store the same fields. The editor
 * mirrors this list in assets/js/constants/techRiderPatchColumns.js, pinned by TechRiderPatchColumnsTest.
 */
enum TechRiderPatchColumn: string
{
    case Channel = 'channel';
    case Name = 'name';
    case Microphone = 'microphone';
    case Routing = 'routing';
    case Colour = 'colour';

    public function label(): string
    {
        return match ($this) {
            self::Channel => 'Canal',
            self::Name => 'Nom',
            self::Microphone => 'Micro',
            self::Routing => 'Routage',
            self::Colour => 'Couleur',
        };
    }
}

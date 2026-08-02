<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * The only colours a tech rider may use, for patch list rows today and for stage plot items
 * once #769 lands.
 *
 * A closed palette rather than a colour picker, for two reasons. Whatever renders the export
 * receives a value from a known list instead of arbitrary user CSS, and one definition keeps
 * the colours identical wherever they appear.
 *
 * Colour is load bearing here, not decoration: a patch list is read by grouping rows by
 * destination, and that grouping has to survive into the exported document.
 *
 * The stored value is the case name, not the hex. Renaming a colour or correcting a shade is
 * then a change in one place instead of an UPDATE across every rider ever written.
 *
 * Kept in step with assets/js/constants/techRiderColours.js by
 * tests/Unit/Enum/BandSpace/TechRiderColourPaletteTest.php, which fails if either side drifts.
 */
enum TechRiderColour: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Cyan = 'cyan';
    case Purple = 'purple';
    case Grey = 'grey';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function hex(): string
    {
        return match ($this) {
            self::Red => '#dc2626',
            self::Orange => '#ea580c',
            self::Yellow => '#ca8a04',
            self::Green => '#16a34a',
            self::Cyan => '#0891b2',
            self::Purple => '#7c3aed',
            self::Grey => '#6b7280',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Red => 'Rouge',
            self::Orange => 'Orange',
            self::Yellow => 'Jaune',
            self::Green => 'Vert',
            self::Cyan => 'Cyan',
            self::Purple => 'Violet',
            self::Grey => 'Gris',
        };
    }
}

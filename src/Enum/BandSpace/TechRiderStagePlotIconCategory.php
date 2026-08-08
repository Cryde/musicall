<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * How the icon picker groups its catalogue.
 *
 * Twenty-one icons in one flat list is a scroll; grouped, a guitarist looking for a cabinet
 * knows which third of the list to read.
 */
enum TechRiderStagePlotIconCategory: string
{
    case Audio = 'audio';
    case Instrument = 'instrument';
    case Lighting = 'lighting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Audio => 'Son',
            self::Instrument => 'Instruments',
            self::Lighting => 'Lumière',
            self::Other => 'Divers',
        };
    }

    /**
     * What a drawn symbol takes when its element has no colour of its own. Same four values the
     * placeholder PNGs are painted with, which until now existed only as pixels and a README table.
     */
    public function hex(): string
    {
        return match ($this) {
            self::Audio => '#2563eb',
            self::Instrument => '#059669',
            self::Lighting => '#d97706',
            self::Other => '#64748b',
        };
    }
}

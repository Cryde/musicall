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
}

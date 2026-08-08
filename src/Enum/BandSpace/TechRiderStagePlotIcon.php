<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * What can be placed on a stage plot.
 *
 * An enum rather than a table: the icons ship with the application, so a table would mean a
 * fixture to keep in sync and a join to read something static. The value is the slug, and the
 * slug is also the filename, so there is one string to get right per icon.
 *
 * The first pass is what the reference rider actually places. Adding one means adding a case
 * and dropping the matching PNG in place; TechRiderStagePlotIconArtworkTest fails if either
 * half is missing.
 */
enum TechRiderStagePlotIcon: string
{
    case DrumKit = 'drum_kit';
    case DrumStool = 'drum_stool';
    case GuitarAmp = 'guitar_amp';
    case BassAmp = 'bass_amp';
    case Keyboard = 'keyboard';
    case KeyboardStand = 'keyboard_stand';
    case VocalMic = 'vocal_mic';
    case InstrumentMic = 'instrument_mic';
    case DiBox = 'di_box';
    case WedgeMonitor = 'wedge_monitor';
    case InEarPack = 'in_ear_pack';
    case MixingDesk = 'mixing_desk';
    case IoRack = 'io_rack';
    case PowerSocket = 'power_socket';
    case ParCan = 'par_can';
    case LightBar = 'light_bar';
    case Strobe = 'strobe';
    case Riser = 'riser';
    case MusicStand = 'music_stand';
    case Laptop = 'laptop';
    case PersonMarker = 'person_marker';

    /** Directory only, so the path is built in one place and the tests can check it. */
    public const string IMAGE_DIRECTORY = 'images/band_space/stage_plot';

    /**
     * Under assets/ rather than public/ because a symbol is inlined, never fetched by URL: an SVG
     * behind an <img> is an isolated document that the page's colour cannot reach, so currentColor
     * would render black.
     */
    public const string SYMBOL_DIRECTORY = 'assets/icons/stage_plot';

    /** @return list<string> for Assert\Choice, which cannot take an enum directly here. */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Under public/ and unhashed, unlike anything in assets/ which Vite fingerprints at build.
     * A stable path means the picker, the stored document and any future server side renderer
     * all name the same file, and a renderer reading from disk can predict the filename.
     */
    public function imagePath(): string
    {
        return sprintf('/%s/%s.png', self::IMAGE_DIRECTORY, $this->value);
    }

    /**
     * Null is the normal state, not an error: symbols are drawn in batches so the stroke weight can
     * be calibrated on a real print first. A null means fall back to the placeholder PNG.
     */
    public function symbolPath(): ?string
    {
        return match ($this) {
            self::DrumKit,
            self::WedgeMonitor,
            self::GuitarAmp,
            self::BassAmp,
            self::VocalMic,
            self::ParCan,
            self::PowerSocket => sprintf('%s/%s.svg', self::SYMBOL_DIRECTORY, $this->value),

            default => null,
        };
    }

    public function category(): TechRiderStagePlotIconCategory
    {
        return match ($this) {
            self::VocalMic,
            self::InstrumentMic,
            self::DiBox,
            self::WedgeMonitor,
            self::InEarPack,
            self::MixingDesk,
            self::IoRack => TechRiderStagePlotIconCategory::Audio,

            self::DrumKit,
            self::DrumStool,
            self::GuitarAmp,
            self::BassAmp,
            self::Keyboard,
            self::KeyboardStand => TechRiderStagePlotIconCategory::Instrument,

            self::ParCan,
            self::LightBar,
            self::Strobe => TechRiderStagePlotIconCategory::Lighting,

            self::PowerSocket,
            self::Riser,
            self::MusicStand,
            self::Laptop,
            self::PersonMarker => TechRiderStagePlotIconCategory::Other,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DrumKit => 'Batterie',
            self::DrumStool => 'Siège de batterie',
            self::GuitarAmp => 'Ampli guitare',
            self::BassAmp => 'Ampli basse',
            self::Keyboard => 'Clavier',
            self::KeyboardStand => 'Stand de clavier',
            self::VocalMic => 'Micro chant sur pied',
            self::InstrumentMic => 'Micro instrument',
            self::DiBox => 'Boîtier de direct',
            self::WedgeMonitor => 'Retour de scène',
            self::InEarPack => 'Pack in-ear',
            self::MixingDesk => 'Console',
            self::IoRack => 'Rack I/O',
            self::PowerSocket => 'Prise secteur 220v',
            self::ParCan => 'Projecteur PAR',
            self::LightBar => 'Barre de LED',
            self::Strobe => 'Stroboscope',
            self::Riser => 'Praticable',
            self::MusicStand => 'Pupitre',
            self::Laptop => 'Ordinateur portable',
            self::PersonMarker => 'Emplacement musicien',
        };
    }
}

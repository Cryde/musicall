<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song;

use App\Entity\BandSpace\Lyrics;
use App\Entity\BandSpace\Song;
use App\Service\BandSpace\Lyrics\LyricsSingerResolver;
use App\Service\BandSpace\Song\ChordPro\ChordProParser;
use App\Service\BandSpace\Song\ChordPro\ChordTransposer;
use App\Service\BandSpace\Song\ChordPro\SingerPalette;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A song ready to print (#1055): its sheet, transposed if asked, and every singer it names with a
 * name and a colour. What the song PDF and the setlist's « Inclure les paroles » both render.
 *
 * @phpstan-import-type SheetBlock from ChordProParser
 *
 * @phpstan-type Singer array{name: string, color: string, former: bool}
 * @phpstan-type SongSheet array{
 *     title: string,
 *     tonality: string|null,
 *     tempo: int|null,
 *     blocks: list<SheetBlock>,
 *     singers: array<string, Singer>,
 *     show_chords: bool,
 *     show_singers: bool,
 *     section_labels: array<string, string>,
 * }
 */
readonly class SongSheetBuilder
{
    private const string ALL_LABEL = 'Tous';
    private const string UNKNOWN_KEY = 'band_space.song.unknown_member';

    public function __construct(
        private ChordProParser $parser,
        private ChordTransposer $transposer,
        private LyricsSingerResolver $singerResolver,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return SongSheet
     */
    public function build(Song $song, ?Lyrics $lyrics, SongSheetOptions $options): array
    {
        return $this->sheet($song->title, $song, $lyrics, $options);
    }

    /**
     * Linked lyrics print as their song. Unlinked ones only have a title: no key, no tempo.
     *
     * @return SongSheet
     */
    public function buildFromLyrics(Lyrics $lyrics, SongSheetOptions $options): array
    {
        return $this->sheet($lyrics->song->title ?? $lyrics->title, $lyrics->song, $lyrics, $options);
    }

    /**
     * @return SongSheet
     */
    private function sheet(string $title, ?Song $song, ?Lyrics $lyrics, SongSheetOptions $options): array
    {
        $tonality = $song?->tonality;
        $source = $options->transpose === 0
            ? $lyrics?->content
            : $this->transposer->transposeSource($lyrics?->content, $options->transpose, $tonality);

        return [
            'title' => $title,
            'tonality' => $options->transpose === 0
                ? $tonality
                : ($this->transposer->transposeKey($tonality, $options->transpose) ?? $tonality),
            'tempo' => $song?->tempo,
            'blocks' => $this->parser->sheet($source),
            'singers' => $options->showSingers && $lyrics instanceof Lyrics ? $this->singers($lyrics) : [],
            'show_chords' => $options->showChords,
            'show_singers' => $options->showSingers,
            'section_labels' => ChordProParser::SECTION_KINDS,
        ];
    }

    /**
     * @return array<string, array{name: string, color: string, former: bool}>
     */
    private function singers(Lyrics $lyrics): array
    {
        $members = [];
        foreach ($this->singerResolver->singers((string) $lyrics->bandSpace->id, $lyrics->content) as $singer) {
            $members[$singer['id']] = $singer;
        }

        $singers = [];
        $index = 0;
        foreach ($this->parser->singerIds($lyrics->content) as $id) {
            if ($id === ChordProParser::SINGER_ALL) {
                $singers[$id] = ['name' => self::ALL_LABEL, 'color' => SingerPalette::ALL, 'former' => false];
                continue;
            }
            $singers[$id] = [
                'name' => $members[$id]['name'] ?? $this->translator->trans(self::UNKNOWN_KEY),
                'color' => SingerPalette::forIndex($index++),
                'former' => $members[$id]['is_former_member'] ?? false,
            ];
        }

        return $singers;
    }
}

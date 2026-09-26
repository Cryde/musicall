<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song;

use App\Entity\BandSpace\Song;
use App\Service\BandSpace\Song\ChordPro\ChordProParser;
use App\Service\BandSpace\Song\ChordPro\ChordTransposer;
use App\Service\BandSpace\Song\ChordPro\SingerPalette;
use App\Service\Builder\BandSpace\SongLyricsBuilder;

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
    private const string UNKNOWN_LABEL = 'Membre inconnu';

    public function __construct(
        private ChordProParser $parser,
        private ChordTransposer $transposer,
        private SongLyricsBuilder $lyricsBuilder,
    ) {
    }

    /**
     * @return SongSheet
     */
    public function build(Song $song, SongSheetOptions $options): array
    {
        $lyrics = $options->transpose === 0
            ? $song->lyrics
            : $this->transposer->transposeSource($song->lyrics, $options->transpose, $song->tonality);

        return [
            'title' => $song->title,
            'tonality' => $options->transpose === 0
                ? $song->tonality
                : ($this->transposer->transposeKey($song->tonality, $options->transpose) ?? $song->tonality),
            'tempo' => $song->tempo,
            'blocks' => $this->parser->sheet($lyrics),
            'singers' => $options->showSingers ? $this->singers($song) : [],
            'show_chords' => $options->showChords,
            'show_singers' => $options->showSingers,
            'section_labels' => ChordProParser::SECTION_KINDS,
        ];
    }

    /**
     * @return array<string, array{name: string, color: string, former: bool}>
     */
    private function singers(Song $song): array
    {
        $members = [];
        foreach ($this->lyricsBuilder->build($song)->singers as $singer) {
            $members[$singer['id']] = $singer;
        }

        $singers = [];
        $index = 0;
        foreach ($this->parser->singerIds($song->lyrics) as $id) {
            if ($id === ChordProParser::SINGER_ALL) {
                $singers[$id] = ['name' => self::ALL_LABEL, 'color' => SingerPalette::ALL, 'former' => false];
                continue;
            }
            $singers[$id] = [
                'name' => $members[$id]['name'] ?? self::UNKNOWN_LABEL,
                'color' => SingerPalette::forIndex($index++),
                'former' => $members[$id]['is_former_member'] ?? false,
            ];
        }

        return $singers;
    }
}

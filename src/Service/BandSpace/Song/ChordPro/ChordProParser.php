<?php declare(strict_types=1);

namespace App\Service\BandSpace\Song\ChordPro;

/**
 * Reads song lyrics written in ChordPro (#1055), the server twin of assets/js/utils/chordpro.js.
 * Both run against tests/Fixtures/ChordPro/cases.json, so the PDF reads a song the way the drawer does.
 *
 * Singers are our extension: `<span singer="@[userId] @[userId]">…</span>`, or `singer="all"`.
 *
 * @phpstan-type Segment array{text: string, singers: list<string>|null}
 * @phpstan-type Chunk array{chords: list<string>, segments: list<Segment>}
 * @phpstan-type SheetLine array{type: 'line', chunks: list<Chunk>, singerSets: list<list<string>>}
 * @phpstan-type SheetLeaf SheetLine|array{type: 'comment', text: string}|array{type: 'directive', raw: string}
 * @phpstan-type SheetBlock SheetLeaf|array{type: 'section', kind: string, label: string, content: list<SheetLeaf>}
 * @phpstan-type Range array{start: int, end: int, singers: list<string>}
 * @phpstan-type Line array{text: string, chords: list<array{pos: int, name: string}>, ranges: list<Range>}
 */
final readonly class ChordProParser
{
    public const string SINGER_ALL = 'all';

    /**
     * The parts this app draws, in song order, with the name the band reads. ChordPro 6 allows any
     * `start_of_<name>`; another one (`start_of_tab`) stays a directive. Mirrors SECTION_KINDS in chordpro.js.
     */
    public const array SECTION_KINDS = [
        'intro' => 'Intro',
        'verse' => 'Couplet',
        'prechorus' => 'Pré-refrain',
        'chorus' => 'Refrain',
        'bridge' => 'Pont',
        'break' => 'Break',
        'solo' => 'Solo',
        'interlude' => 'Interlude',
        'outro' => 'Outro',
    ];
    private const array KIND_ALIASES = ['v' => 'verse', 'c' => 'chorus', 'b' => 'bridge', 'pre_chorus' => 'prechorus'];
    private const string SECTION_DIRECTIVE = '/^(?:(start|end)_of_([a-z_]+)|(so|eo)([vcb]))$/';
    private const array COMMENTS = ['comment', 'c'];
    public const string DIRECTIVE = '/^\{\s*([a-z_]+)\s*(?::\s*(.*?))?\s*\}$/i';
    private const string SPAN_OPEN = '/^<span singer="([^"]*)">/u';
    private const string SPAN_CLOSE = '</span>';

    /**
     * @return list<SheetBlock>
     */
    public function sheet(?string $source): array
    {
        $blocks = [];
        /** @var array{kind: string, label: string}|null $section */
        $section = null;
        /** @var list<SheetLeaf> $content */
        $content = [];

        foreach ($this->lines($source) as $raw) {
            if (preg_match(self::DIRECTIVE, trim($raw), $directive) !== 1) {
                $leaf = $this->sheetLine($this->parseLine($raw));
            } else {
                $name = strtolower($directive[1]);
                $value = $directive[2] ?? '';
                $edge = $this->sectionDirective($name);
                if ($edge !== null) {
                    if ($section !== null) {
                        $blocks[] = ['type' => 'section', 'kind' => $section['kind'], 'label' => $section['label'], 'content' => $content];
                    }
                    $section = $edge['edge'] === 'start' ? ['kind' => $edge['kind'], 'label' => $value] : null;
                    $content = [];
                    continue;
                }
                $leaf = in_array($name, self::COMMENTS, true)
                    ? ['type' => 'comment', 'text' => $value]
                    : ['type' => 'directive', 'raw' => trim($raw)];
            }

            if ($section !== null) {
                $content[] = $leaf;
            } else {
                $blocks[] = $leaf;
            }
        }

        if ($section !== null) {
            $blocks[] = ['type' => 'section', 'kind' => $section['kind'], 'label' => $section['label'], 'content' => $content];
        }

        return $blocks;
    }

    /**
     * Every singer id in order of first appearance, `all` included. That order picks the colours.
     *
     * @return list<string>
     */
    public function singerIds(?string $source): array
    {
        $ids = [];
        foreach ($this->lines($source) as $raw) {
            if (preg_match(self::DIRECTIVE, trim($raw)) === 1) {
                continue;
            }
            foreach ($this->parseLine($raw)['ranges'] as $range) {
                foreach ($range['singers'] as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return array_map('strval', array_keys($ids));
    }

    /**
     * `start_of_solo` is start/solo, `eoc` is end/chorus, anything else null.
     *
     * @return array{edge: 'start'|'end', kind: string}|null
     */
    private function sectionDirective(string $name): ?array
    {
        if (preg_match(self::SECTION_DIRECTIVE, $name, $match) !== 1) {
            return null;
        }
        $edge = $match[1] !== '' ? $match[1] : ($match[3] === 'so' ? 'start' : 'end');
        $raw = $match[2] !== '' ? $match[2] : $match[4];
        $kind = self::KIND_ALIASES[$raw] ?? $raw;

        return isset(self::SECTION_KINDS[$kind]) ? ['edge' => $edge === 'start' ? 'start' : 'end', 'kind' => $kind] : null;
    }

    /**
     * @return list<string>
     */
    private function lines(?string $source): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $source ?? ''));
    }

    /**
     * Positions count code points. They never leave this class.
     *
     * @return Line
     */
    private function parseLine(string $raw): array
    {
        $characters = mb_str_split($raw);
        $count = count($characters);
        $text = '';
        $length = 0;
        $chords = [];
        $ranges = [];
        $open = null;
        $index = 0;

        while ($index < $count) {
            $character = $characters[$index];
            if ($character === '[') {
                $close = $this->indexOf($characters, ']', $index);
                $name = $close !== null ? trim(implode('', array_slice($characters, $index + 1, $close - $index - 1))) : '';
                if ($close !== null && $name !== '') {
                    $chords[] = ['pos' => $length, 'name' => $name];
                    $index = $close + 1;
                    continue;
                }
            }
            if ($character === '<') {
                $rest = implode('', array_slice($characters, $index));
                if (preg_match(self::SPAN_OPEN, $rest, $opening) === 1) {
                    if ($open !== null) {
                        $ranges[] = [...$open, 'end' => $length];
                    }
                    $singers = $this->parseSingers($opening[1]);
                    $open = $singers !== [] ? ['start' => $length, 'singers' => $singers] : null;
                    $index += mb_strlen($opening[0]);
                    continue;
                }
                if (str_starts_with($rest, self::SPAN_CLOSE)) {
                    if ($open !== null) {
                        $ranges[] = [...$open, 'end' => $length];
                        $open = null;
                    }
                    $index += mb_strlen(self::SPAN_CLOSE);
                    continue;
                }
            }
            $text .= $character;
            ++$length;
            ++$index;
        }
        if ($open !== null) {
            $ranges[] = [...$open, 'end' => $length];
        }

        return [
            'text' => $text,
            'chords' => $chords,
            'ranges' => array_values(array_filter($ranges, static fn (array $range): bool => $range['end'] > $range['start'])),
        ];
    }

    /**
     * @param list<string> $characters
     */
    private function indexOf(array $characters, string $needle, int $from): ?int
    {
        $count = count($characters);
        for ($index = $from + 1; $index < $count; ++$index) {
            if ($characters[$index] === $needle) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function parseSingers(string $attribute): array
    {
        if (trim($attribute) === self::SINGER_ALL) {
            return [self::SINGER_ALL];
        }
        preg_match_all('/@\[([^\]]+)\]/', $attribute, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param Line $line
     *
     * @return SheetLine
     */
    private function sheetLine(array $line): array
    {
        $length = mb_strlen($line['text']);
        $cuts = array_values(array_unique([0, ...array_column($line['chords'], 'pos'), $length]));
        sort($cuts);

        $chunks = [];
        foreach ($cuts as $position => $from) {
            $to = $cuts[$position + 1] ?? $from;
            $chords = array_column(
                array_filter($line['chords'], static fn (array $chord): bool => $chord['pos'] === $from),
                'name',
            );
            if ($from === $to && $chords === [] && $from === $length && $chunks !== []) {
                break;
            }
            $chunks[] = ['chords' => $chords, 'segments' => $this->segmentsBetween($line, $from, $to)];
        }

        return ['type' => 'line', 'chunks' => $chunks, 'singerSets' => $this->singerSets($line)];
    }

    /**
     * @param Line $line
     *
     * @return list<Segment>
     */
    private function segmentsBetween(array $line, int $from, int $to): array
    {
        $bounds = [$from, $to];
        foreach ($line['ranges'] as $range) {
            foreach ([$range['start'], $range['end']] as $bound) {
                if ($bound > $from && $bound < $to) {
                    $bounds[] = $bound;
                }
            }
        }
        $bounds = array_values(array_unique($bounds));
        sort($bounds);

        $segments = [];
        $last = count($bounds) - 1;
        for ($index = 0; $index < $last; ++$index) {
            $start = $bounds[$index];
            $singers = null;
            foreach ($line['ranges'] as $range) {
                if ($start >= $range['start'] && $start < $range['end']) {
                    $singers = $range['singers'];
                    break;
                }
            }
            $segments[] = ['text' => mb_substr($line['text'], $start, $bounds[$index + 1] - $start), 'singers' => $singers];
        }

        return $segments;
    }

    /**
     * @param Line $line
     *
     * @return list<list<string>>
     */
    private function singerSets(array $line): array
    {
        $ranges = $line['ranges'];
        usort($ranges, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $sets = [];
        foreach ($ranges as $range) {
            $sets[implode(' ', $range['singers'])] ??= $range['singers'];
        }

        return array_values($sets);
    }
}

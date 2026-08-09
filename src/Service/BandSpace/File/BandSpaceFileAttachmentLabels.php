<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

/**
 * French wording for the sources a file can hang on.
 *
 * Shared by the two delete paths that refuse to trash an attached file. Those two paths silently
 * disagreeing on what "attached" means is the bug this class exists to prevent, so the list of
 * sources is built once and both sentences are written around it.
 *
 * assets/js/constants/fileSources.js carries the same five nouns, for the screens that warn about the
 * refusal before it happens. A source type added to BandSpaceFileSourceTypes::ALL is two edits, one
 * here and one there, and BandSpaceFileAttachmentLabelsTest fails until this half is done.
 */
final class BandSpaceFileAttachmentLabels
{
    private const array NOUN_BY_SOURCE_TYPE = [
        'task' => 'une tâche',
        'finance' => 'une entrée financière',
        'note' => 'une note',
        'song' => 'une chanson',
        'setlist' => 'une setlist',
    ];

    /**
     * Enumerates the given source types as a French list, for example "une tâche et une note".
     *
     * @param string[] $sourceTypes
     */
    public static function describe(array $sourceTypes): string
    {
        $labels = array_values(array_unique(array_map(
            static fn (string $sourceType): string => self::NOUN_BY_SOURCE_TYPE[$sourceType] ?? 'une autre ressource',
            $sourceTypes,
        )));

        return match (count($labels)) {
            0 => 'une autre ressource',
            1 => $labels[0],
            2 => $labels[0] . ' et ' . $labels[1],
            default => implode(', ', array_slice($labels, 0, -1)) . ' et ' . end($labels),
        };
    }
}

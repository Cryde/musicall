<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Wording for the sources a file can hang on, in the reader's language.
 *
 * Shared by every path that refuses to trash an attached file. Those paths silently disagreeing on
 * what "attached" means is the bug this class exists to prevent, so the list of sources is built once
 * and each sentence is written around it.
 *
 * assets/js/constants/fileSources.js carries the same five nouns, for the screens that warn about the
 * refusal before it happens. A source type added to BandSpaceFileSourceTypes::ALL is two edits, one
 * here and one there, and BandSpaceFileAttachmentLabelsTest fails until this half is done.
 */
readonly class BandSpaceFileAttachmentLabels
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Enumerates the given source types as a list, for example "une note et une tâche", worded like the
     * front end's Intl.ListFormat so the warning and the refusal read the same.
     *
     * @param string[] $sourceTypes
     */
    public function describe(array $sourceTypes): string
    {
        // Sorted so the sentence does not follow whatever order the database returned the rows in.
        sort($sourceTypes);
        $labels = array_values(array_unique(array_map($this->noun(...), $sourceTypes)));

        return match (count($labels)) {
            0 => $this->noun(''),
            1 => $labels[0],
            2 => $this->translator->trans('band_space.file.attached_source.list_pair', [
                'first' => $labels[0],
                'second' => $labels[1],
            ]),
            default => $this->translator->trans('band_space.file.attached_source.list_many', [
                'head' => implode(', ', array_slice($labels, 0, -1)),
                'last' => end($labels),
            ]),
        };
    }

    private function noun(string $sourceType): string
    {
        return match ($sourceType) {
            'task' => $this->translator->trans('band_space.file.attached_source.task'),
            'finance' => $this->translator->trans('band_space.file.attached_source.finance'),
            'note' => $this->translator->trans('band_space.file.attached_source.note'),
            'song' => $this->translator->trans('band_space.file.attached_source.song'),
            'setlist' => $this->translator->trans('band_space.file.attached_source.setlist'),
            default => $this->translator->trans('band_space.file.attached_source.other'),
        };
    }
}

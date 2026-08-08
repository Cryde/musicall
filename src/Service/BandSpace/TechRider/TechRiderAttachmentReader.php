<?php declare(strict_types=1);

namespace App\Service\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\BandSpace\TechRiderItem;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Spools a document item's file to a local temp file so the export can place it, and decides when it
 * cannot be placed at all.
 *
 * Every refusal returns a reference instead of throwing. A rider that silently drops an attachment is
 * worse than one that names it: the band can see what to send separately, and one bad file cannot
 * stop the whole export.
 *
 * Bytes are copied stream to stream and never materialise in this process. That is not tidiness: a
 * band space file may be 500 MiB, and although the size check below refuses anything that large, the
 * copy is written so that a stale or wrong size cannot turn into a fatal.
 */
readonly class TechRiderAttachmentReader
{
    private const array IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** Read size for the encryption scan. The whole file is covered, this is just the chunk. */
    private const int SCAN_CHUNK_BYTES = 65536;

    public function __construct(
        private StorageInterface $vichStorage,
    ) {
    }

    /**
     * @return array{kind: 'merge'|'image', path: string, name: string}|array{kind: 'reference', name: string, reason: string}
     */
    public function prepare(TechRiderItem $item, string $workspace, int $maxBytes): array
    {
        $file = $item->file;
        if (!$file instanceof BandSpaceFile) {
            return self::reference('Aucun fichier', 'Aucun fichier n\'est joint à cet élément.');
        }

        $name = $file->originalName;

        if ($file->isArchived()) {
            return self::reference($name, 'Ce fichier a été supprimé de l\'espace et n\'a pas pu être inclus.');
        }

        $version = $file->currentVersion;
        if (!$version instanceof BandSpaceFileVersion) {
            return self::reference($name, 'Aucune version disponible pour ce fichier.');
        }

        // Re-checked here on purpose: the type is validated when the file is attached, and a later
        // version upload can replace it with something that has no visual form at all.
        if (!BandSpaceFileMimeAllowlist::isRenderableAsPage($version->mimeType)) {
            return self::reference($name, 'Ce type de fichier ne peut pas être inclus dans le document.');
        }

        if ($version->size !== null && $version->size > $maxBytes) {
            return self::reference($name, sprintf(
                'Fichier trop volumineux pour être inclus (%s Mo). À envoyer séparément.',
                number_format($version->size / (1024 * 1024), 1, ',', ' '),
            ));
        }

        $extension = self::IMAGE_EXTENSIONS[$version->mimeType] ?? 'pdf';
        $path = sprintf('%s/attachment-%s.%s', $workspace, bin2hex(random_bytes(6)), $extension);

        $failure = $this->spool($version, $path, $maxBytes);
        if ($failure !== null) {
            return self::reference($name, $failure);
        }

        if ($extension !== 'pdf') {
            return ['kind' => 'image', 'path' => $path, 'name' => $name];
        }

        $refusal = $this->pdfRefusal($path);

        return $refusal === null
            ? ['kind' => 'merge', 'path' => $path, 'name' => $name]
            : self::reference($name, $refusal);
    }

    /**
     * Copies storage to disk. Returns null on success, or the reason to print instead.
     *
     * The reason matters rather than a bare boolean: an object that turns out to be larger than its
     * version row claimed is a different thing from one that is not there, and telling a band their
     * file is missing when it is merely oversized sends them looking for the wrong problem.
     */
    private function spool(BandSpaceFileVersion $version, string $path, int $maxBytes): ?string
    {
        $source = $this->vichStorage->resolveStream($version, 'uploadedFile');
        if (!is_resource($source)) {
            return 'Le fichier est introuvable dans le stockage.';
        }

        $target = @fopen($path, 'wb');
        if ($target === false) {
            fclose($source);

            return 'Le fichier n\'a pas pu être préparé pour le document.';
        }

        try {
            // One byte past the cap on purpose: copying exactly the cap cannot tell a file that fits
            // from one that was truncated at the limit, so the overshoot is what detects the second.
            $copied = stream_copy_to_stream($source, $target, $maxBytes + 1);

            if ($copied === false) {
                return 'Le fichier est introuvable dans le stockage.';
            }

            // Reached only when the version row disagrees with the object actually in storage.
            return $copied > $maxBytes
                ? 'Fichier trop volumineux pour être inclus. À envoyer séparément.'
                : null;
        } finally {
            fclose($target);
            fclose($source);
        }
    }

    /**
     * Why this PDF cannot be merged, or null when it can.
     *
     * Both checks are cheap and local, and both fail safe: the worst case of a false positive is that
     * a perfectly good attachment is named rather than merged, where the alternative is a merge that
     * fails and takes the whole export down with it.
     */
    private function pdfRefusal(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return 'Le fichier n\'a pas pu être lu.';
        }

        try {
            if (fread($handle, 5) !== '%PDF-') {
                return 'Ce fichier n\'est pas un PDF valide et n\'a pas pu être inclus.';
            }

            // A password protected PDF cannot be merged, so it is named instead.
            //
            // The whole file is scanned rather than the tail. The encryption dictionary is referenced
            // from the trailer, which is usually at the end, but not dependably: a file with many
            // objects pushes its trailer past any fixed window, and a document saved incrementally
            // carries an earlier trailer further up. The scan is bounded anyway by the size cap
            // above, so there is nothing to buy by guessing where to look.
            //
            // It can still miss, because a cross reference stream keeps the trailer compressed. The
            // consequence of a miss is a failed merge, which is why this is a courtesy rather than
            // the guarantee.
            fseek($handle, 0);
            $carry = '';
            while (!feof($handle)) {
                $chunk = fread($handle, self::SCAN_CHUNK_BYTES);
                if (!is_string($chunk)) {
                    break;
                }

                if (str_contains($carry . $chunk, '/Encrypt')) {
                    return 'Ce PDF est protégé par un mot de passe et n\'a pas pu être inclus.';
                }

                // Enough overlap that the marker cannot hide across a chunk boundary.
                $carry = substr($chunk, -8);
            }

            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{kind: 'reference', name: string, reason: string}
     */
    private static function reference(string $name, string $reason): array
    {
        return ['kind' => 'reference', 'name' => $name, 'reason' => $reason];
    }
}

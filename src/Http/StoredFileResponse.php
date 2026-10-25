<?php declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * A stored upload streamed back through an access-checked endpoint: the band space files and the chat
 * media of both kinds of conversation (#1247).
 */
final class StoredFileResponse
{
    /**
     * @param object $uploadable the Vich entity holding the file in `$fieldName`
     * @param bool   $inline     for chat media, which the page shows in place
     */
    public static function stream(
        StorageInterface $vichStorage,
        object $uploadable,
        string $fieldName,
        string $mimeType,
        string $fileName,
        ?int $size,
        bool $inline = false,
    ): StreamedResponse {
        $stream = $vichStorage->resolveStream($uploadable, $fieldName);
        if ($stream === null) {
            throw new NotFoundHttpException('band_space.file.version_binary_not_found');
        }

        $response = new StreamedResponse(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $mimeType);
        // Stop browsers from MIME-sniffing the body into an executable type
        // (defence-in-depth alongside the attachment disposition below).
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // ContentDisposition sanitises the (user-supplied) original filename so a
        // non-ASCII or "/"-containing name cannot 500 the download.
        $response->headers->set(
            'Content-Disposition',
            $inline ? ContentDisposition::inline($fileName) : ContentDisposition::attachment($fileName),
        );
        if ($size !== null) {
            $response->headers->set('Content-Length', (string) $size);
        }

        return $response;
    }
}

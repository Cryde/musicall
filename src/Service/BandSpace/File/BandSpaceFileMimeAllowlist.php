<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

final class BandSpaceFileMimeAllowlist
{
    public const int MAX_UPLOAD_SIZE_BYTES = 500 * 1024 * 1024;

    /**
     * MIME types accepted by the band-space Files module. Extend cautiously:
     * adding a type here exposes it to public download links.
     */
    public const array ALLOWED = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/zip',
        'application/x-tar',
        'application/x-gzip',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'audio/mpeg',
        'audio/mp4',
        'audio/wav',
        'audio/x-wav',
        'audio/flac',
        'audio/x-flac',
        'audio/aac',
        'audio/ogg',
        'audio/webm',
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    public static function isAllowed(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED, true);
    }
}

<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Exception\TranslatableHttpException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * The type check of every band space upload. A Guitar Pro 3 to 6 file is let through as the
 * octet-stream it is detected as, without putting octet-stream itself on the allowlist.
 */
readonly class BandSpaceUploadTypeGuard
{
    private const string UNKNOWN_TYPE = 'application/octet-stream';

    /**
     * @return string the detected MIME type, the one to store
     */
    public function assertAllowed(File $upload): string
    {
        $mimeType = $upload->getMimeType() ?? self::UNKNOWN_TYPE;
        if (BandSpaceFileMimeAllowlist::isAllowed($mimeType)) {
            return $mimeType;
        }

        if ($mimeType === self::UNKNOWN_TYPE && $this->isGuitarProFile($upload)) {
            return $mimeType;
        }

        throw new TranslatableHttpException(
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            'band_space.file.type_not_allowed',
            ['type' => $mimeType],
        );
    }

    private function isGuitarProFile(File $upload): bool
    {
        $name = $upload instanceof UploadedFile ? $upload->getClientOriginalName() : $upload->getFilename();

        return GuitarProSignature::matchesFile($name, $upload->getPathname());
    }
}

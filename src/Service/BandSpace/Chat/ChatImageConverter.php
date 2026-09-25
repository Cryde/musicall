<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use Imagine\Filter\Basic\Autorotate;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * Turns a pasted or dropped chat image into what is stored (#973): WebP, long edge capped, never
 * upscaled, metadata gone.
 *
 * Re-encoding is also what strips EXIF, so a phone photo stops carrying its GPS position. Orientation
 * is applied first for that very reason: once EXIF is gone, a portrait photo would land sideways.
 */
readonly class ChatImageConverter
{
    /** Long enough for a photographed setlist or rider page to stay readable. */
    public const int MAX_EDGE_PX = 1600;

    public const string OUTPUT_MIME_TYPE = 'image/webp';

    /** SVG stays out, as it does for every band space file: it can carry inline scripts. */
    public const array ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const string MAX_UPLOAD_SIZE = '25M';

    /**
     * GD decodes to four bytes a pixel before anything is resized, so this caps the memory one upload
     * can take (about 120 MB, under the 256 MB limit) and refuses a decompression bomb unread.
     */
    public const int MAX_PIXELS = 30_000_000;

    private const int WEBP_QUALITY = 82;

    /** GD would keep only the first frame, so an animated GIF is stored as it came. */
    private const string PASSTHROUGH_MIME_TYPE = 'image/gif';

    public function convert(File $image, string $mimeType): ConvertedChatImage
    {
        if ($mimeType === self::PASSTHROUGH_MIME_TYPE) {
            return new ConvertedChatImage($image, $mimeType, false);
        }

        $imagine = new Imagine();
        $decoded = $imagine->open($image->getPathname());
        (new Autorotate())->apply($decoded);
        $decoded->strip();

        $size = $decoded->getSize();
        if (max($size->getWidth(), $size->getHeight()) > self::MAX_EDGE_PX) {
            $decoded->resize($this->fitWithin($size->getWidth(), $size->getHeight()), ImageInterface::FILTER_LANCZOS);
        }

        $path = tempnam(sys_get_temp_dir(), 'chat_image_');
        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary file for a chat image');
        }
        try {
            $decoded->save($path, ['format' => 'webp', 'webp_quality' => self::WEBP_QUALITY]);
        } catch (\Throwable $e) {
            unlink($path);
            throw $e;
        }

        // ReplacingFile, not File: VichUploader silently skips anything that is not an upload or one.
        return new ConvertedChatImage(new ReplacingFile($path), self::OUTPUT_MIME_TYPE, true);
    }

    private function fitWithin(int $width, int $height): Box
    {
        $ratio = self::MAX_EDGE_PX / max($width, $height);

        return new Box(max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
    }
}

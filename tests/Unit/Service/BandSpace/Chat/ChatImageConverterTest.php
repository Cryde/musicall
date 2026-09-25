<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\BandSpace\Chat;

use App\Service\BandSpace\Chat\ChatImageConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class ChatImageConverterTest extends TestCase
{
    /** @var string[] */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];
    }

    public function test_a_large_photo_is_capped_on_its_long_edge_and_stored_as_webp(): void
    {
        $converted = (new ChatImageConverter())->convert($this->jpeg(3200, 2000), 'image/jpeg');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame('image/webp', $converted->mimeType);
        $this->assertTrue($converted->isTemporary);
        $this->assertSame([1600, 1000, IMAGETYPE_WEBP], $this->dimensionsAndType($converted->file));
    }

    public function test_a_portrait_photo_is_capped_on_its_height(): void
    {
        $converted = (new ChatImageConverter())->convert($this->jpeg(1000, 4000), 'image/jpeg');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame([400, 1600, IMAGETYPE_WEBP], $this->dimensionsAndType($converted->file));
    }

    public function test_a_small_image_is_converted_but_never_upscaled(): void
    {
        $image = imagecreatetruecolor(800, 600);
        $path = $this->temporaryPath();
        imagepng($image, $path);

        $converted = (new ChatImageConverter())->convert(new File($path), 'image/png');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame([800, 600, IMAGETYPE_WEBP], $this->dimensionsAndType($converted->file));
    }

    /**
     * A phone shoots in landscape and records "rotate 90° clockwise" in EXIF. Re-encoding drops the
     * EXIF, so the rotation has to be applied first or the photo lands on its side.
     */
    public function test_the_exif_orientation_is_applied_before_the_metadata_is_dropped(): void
    {
        $converted = (new ChatImageConverter())->convert($this->jpeg(2000, 1000, exifOrientation: 6), 'image/jpeg');
        $this->temporaryFiles[] = $converted->file->getPathname();

        $this->assertSame([800, 1600, IMAGETYPE_WEBP], $this->dimensionsAndType($converted->file));
    }

    public function test_a_gif_is_kept_as_it_came_so_its_animation_survives(): void
    {
        $image = imagecreatetruecolor(3000, 3000);
        $path = $this->temporaryPath();
        imagegif($image, $path);
        $gif = new File($path);

        $converted = (new ChatImageConverter())->convert($gif, 'image/gif');

        $this->assertSame($gif, $converted->file);
        $this->assertSame('image/gif', $converted->mimeType);
        $this->assertFalse($converted->isTemporary);
    }

    private function jpeg(int $width, int $height, ?int $exifOrientation = null): File
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();

        if ($exifOrientation !== null) {
            $bytes = $this->withExifOrientation($bytes, $exifOrientation);
        }

        $path = $this->temporaryPath();
        file_put_contents($path, $bytes);

        return new File($path);
    }

    /**
     * GD cannot write EXIF, so the APP1 segment is spliced in by hand, right after the SOI marker: a
     * big endian TIFF header and a single IFD entry, tag 0x0112 (Orientation), type SHORT, count 1.
     */
    private function withExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"
            . "\x00\x01"
            . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00"
            . "\x00\x00\x00\x00";
        $payload = "Exif\x00\x00" . $tiff;
        $segment = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        return substr($jpeg, 0, 2) . $segment . substr($jpeg, 2);
    }

    /**
     * @return array{int, int, int}
     */
    private function dimensionsAndType(File $file): array
    {
        $info = getimagesize($file->getPathname());
        $this->assertIsArray($info);

        return [$info[0], $info[1], $info[2]];
    }

    private function temporaryPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'chat_image_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}

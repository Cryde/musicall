<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\File;

use App\Service\File\RemoteFileDownloader;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class RemoteFileDownloaderTest extends TestCase
{
    #[DataProvider('unsafeUrlProvider')]
    public function test_download_rejects_unsafe_url(string $url): void
    {
        $musicallFilesystem = $this->createMock(FilesystemOperator::class);
        $filesystem = $this->createMock(Filesystem::class);

        // The SSRF guard must reject before any filesystem access happens.
        $filesystem->expects($this->never())->method('copy');
        $musicallFilesystem->expects($this->never())->method('write');

        $downloader = new RemoteFileDownloader(
            $musicallFilesystem,
            $filesystem,
            $this->createStub(LoggerInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $downloader->download($url, '/destination');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'file scheme (local file read)' => ['file:///etc/passwd'],
            'php filter wrapper' => ['php://filter/resource=/etc/passwd'],
            'ftp scheme' => ['ftp://example.com/image.png'],
            'cloud metadata link-local IP' => ['http://169.254.169.254/latest/meta-data/'],
            'loopback IP' => ['http://127.0.0.1/internal'],
            'private RFC1918 IP' => ['http://10.0.0.1/internal'],
            'IPv6 loopback literal' => ['http://[::1]/internal'],
            'IPv4-mapped IPv6 loopback' => ['http://[::ffff:127.0.0.1]/internal'],
            'IPv4-mapped IPv6 link-local metadata' => ['http://[::ffff:169.254.169.254]/'],
            'userinfo trick (real host is metadata IP)' => ['http://google.com@169.254.169.254/secret'],
            'no scheme (bare path)' => ['/etc/passwd'],
        ];
    }
}

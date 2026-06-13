<?php declare(strict_types=1);

namespace App\Service\File;

use League\Flysystem\FilesystemException;
use App\Service\File\Exception\CorruptedFileException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

class RemoteFileDownloader
{
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    public function __construct(
        private readonly FilesystemOperator $musicallFilesystem,
        private readonly Filesystem         $filesystem,
        private readonly LoggerInterface    $logger
    ) {
    }

    /**
     * @return array{string, int}
     * @throws CorruptedFileException
     * @throws FilesystemException
     * @throws \InvalidArgumentException if $path is not a public http(s) URL
     */
    public function download(string $path, string $destinationDir, bool $validateChecksum = true): array
    {
        // SSRF guard: $path is read by Filesystem::copy() and md5_file(), both of
        // which resolve any PHP stream wrapper (file://, php://, http://...). Reject
        // anything that is not a public http(s) URL before touching it, so a future
        // caller passing a user-influenced URL cannot read local files or reach
        // internal/cloud-metadata addresses.
        $this->assertPublicHttpUrl($path);

        $tmpFilePath = tempnam('/tmp', 'remote_file_downloader');
        if ($tmpFilePath === false) {
            throw new \Exception('can not create tmp file for download: ' . $path);
        }

        $this->logger->debug('start of download', [
            'origin' => $path,
            'copy' => $tmpFilePath,
        ]);

        try {
            $this->filesystem->copy($path, $tmpFilePath);
        } catch (\Exception $e) {
            $this->filesystem->remove($tmpFilePath);
            throw $e;
        }

        $filename = sha1(uniqid(time() . '', true)) . '.'. (new File($tmpFilePath))->guessExtension();
        $fullPath = $destinationDir . DIRECTORY_SEPARATOR . $filename;
        $contents = file_get_contents($tmpFilePath);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Failed to read temporary file: %s', $tmpFilePath));
        }
        $this->musicallFilesystem->write($fullPath, $contents);
        $tmpFilePath = $fullPath;

        $this->logger->debug('end of download', [
            'origin' => $path,
            'copy' => $tmpFilePath,
        ]);

        if ($validateChecksum) {
            $tmpFileCheckSum = $this->musicallFilesystem->checksum($tmpFilePath);
            if (md5_file($path) !== $tmpFileCheckSum) {
                $this->logger->warning('file corrupted after download', [
                    'origin' => $path,
                    'copy' => $tmpFilePath,
                    'md5_path' => md5_file($path),
                    'md5_path_tmp' => $tmpFileCheckSum,
                ]);
                $this->musicallFilesystem->delete($tmpFilePath);
                throw new CorruptedFileException(sprintf('file corrupted after download: %s', $tmpFilePath));
            }
        }

        return [$filename, $this->musicallFilesystem->fileSize($tmpFilePath)];
    }

    /**
     * Rejects anything that is not a public http(s) URL: non-http(s) schemes
     * (file://, php://, ftp://, ...) and hosts that resolve to a private or
     * reserved address (loopback, RFC1918, link-local cloud metadata, ...).
     *
     * Known gaps (acceptable for the current trusted callers, all https + public):
     * - Redirects are NOT followed, so a public host that 302-redirects to an
     *   internal IP is not stopped (Filesystem::copy follows redirects via the
     *   http stream wrapper). If a user-controlled URL is ever passed here, switch
     *   to Symfony's NoPrivateNetworkHttpClient, which re-validates each redirect hop.
     * - IPv6-only hosts (AAAA but no A record) are rejected as unresolvable, because
     *   gethostbynamel() only returns IPv4. No current caller is IPv6-only.
     * - Decimal/octal/hex IP notations (e.g. http://2130706433/) are blocked because
     *   they fail DNS resolution, not via an explicit IP-range check.
     */
    private function assertPublicHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException(sprintf('Refusing to download from a malformed URL: %s', $url));
        }

        if (!in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            throw new \InvalidArgumentException(sprintf('Refusing to download from a non-http(s) URL: %s', $url));
        }

        $host = trim($parts['host'], '[]'); // unwrap IPv6 literals such as [::1]

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ips = [$host];
        } else {
            $ips = gethostbynamel($host) ?: [];
            if ($ips === []) {
                throw new \InvalidArgumentException(sprintf('Cannot resolve download host: %s', $host));
            }
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \InvalidArgumentException(sprintf('Refusing to download from a private or reserved address (%s): %s', $ip, $url));
            }
        }
    }
}

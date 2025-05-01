<?php

namespace App\Service\File;

use League\Flysystem\FilesystemException;
use App\Service\File\Exception\CorruptedFileException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

class RemoteFileDownloader
{
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
     */
    public function download(string $path, string $destinationDir): array
    {
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
        $this->musicallFilesystem->write($fullPath, file_get_contents($tmpFilePath));
        $tmpFilePath = $fullPath;

        $this->logger->debug('end of download', [
            'origin' => $path,
            'copy' => $tmpFilePath,
        ]);

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

        return [$filename, $this->musicallFilesystem->fileSize($tmpFilePath)];
    }
}

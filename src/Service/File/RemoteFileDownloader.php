<?php

namespace App\Service\File;

use App\Service\File\Exception\CorruptedFileException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RemoteFileDownloader
{
    private LoggerInterface $logger;
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem, LoggerInterface $logger)
    {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    /**
     * @throws CorruptedFileException
     */
    public function download(string $path, string $destinationDir): UploadedFile
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

        $newFilename = $destinationDir . DIRECTORY_SEPARATOR . sha1(uniqid(time() . '', true)) . '.'. (new File($tmpFilePath))->guessExtension();
        $this->filesystem->rename($tmpFilePath, $newFilename);;
        $tmpFilePath = $newFilename;

        $this->logger->debug('end of download', [
            'origin' => $path,
            'copy' => $tmpFilePath,
        ]);

        if (md5_file($path) !== md5_file($tmpFilePath)) {
            $this->logger->warning('file corrupted after download', [
                'origin' => $path,
                'copy' => $tmpFilePath,
            ]);
            $this->filesystem->remove($tmpFilePath);
            throw new CorruptedFileException(sprintf('file corrupted after download: %s', $tmpFilePath));
        }

        return new UploadedFile($tmpFilePath, $path);
    }
}

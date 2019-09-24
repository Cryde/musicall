<?php

namespace App\Service\File;

use App\Service\File\Exception\CorruptedFileException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RemoteFileDownloader
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * RemoteFileDownloader constructor.
     *
     * @param Filesystem      $filesystem
     * @param LoggerInterface $logger
     */
    public function __construct(Filesystem $filesystem, LoggerInterface $logger)
    {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    /**
     * @param      $path
     * @param bool $withExtension
     *
     * @return UploadedFile
     * @throws \Exception|CorruptedFileException
     */
    public function download($path, $destinationDir)
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

        $newFilename = $destinationDir . DIRECTORY_SEPARATOR . sha1(uniqid(time(), true)) . '.'. (new File($tmpFilePath))->guessExtension();
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

    /**
     * @param $path
     * @param $tmpFilePath
     * @throws ResourceOpenException
     * @throws \Exception
     */
    /**
    protected function copyIntoTmp($path, $tmpFilePath): void
    {
        $tmpResource = $this->fileManager->open($tmpFilePath, 'wb');
        foreach ($this->fileManager->readFromPath($path, 'rb') as $line) {
            $this->fileManager->write($tmpResource, $line);
        }
        $this->fileManager->close($tmpResource);
    }*/
}

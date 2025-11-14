<?php declare(strict_types=1);

namespace App\Fixtures\Publication;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class FakeImageGeneratorStory extends Story
{
    const string RANDOM_PUBLICATION_COVER = 'random_publication_cover';

    public function __construct(
        private readonly ParameterBagInterface $containerBag,
        private readonly FilesystemOperator $musicallFilesystem,
    ) {
    }

    public function build(): void
    {
        $fileInfo = [];
        for ($i = 1; $i <= 12; $i++) {
            $fileName = $i . '.jpg';
            $localFilePath = __DIR__ . '/images/' . $fileName;
            $fullPath = $this->containerBag->get('file_publication_cover_destination') . DIRECTORY_SEPARATOR . $fileName;
            $this->musicallFilesystem->write($fullPath, file_get_contents($localFilePath));

            $fileInfo[] = [$fileName, $this->musicallFilesystem->fileSize($fullPath)];
        }
        $this->addToPool(self::RANDOM_PUBLICATION_COVER, $fileInfo);
    }
}

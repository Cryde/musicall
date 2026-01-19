<?php declare(strict_types=1);

namespace App\Fixtures\Course;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class FakeCourseImageGeneratorStory extends Story
{
    const string RANDOM_COURSE_COVER = 'random_course_cover';

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
            /** @var string $destinationDir */
            $destinationDir = $this->containerBag->get('file_publication_cover_destination');
            $fullPath = $destinationDir . DIRECTORY_SEPARATOR . $fileName;
            $contents = file_get_contents($localFilePath);
            if ($contents === false) {
                throw new \RuntimeException(sprintf('Failed to read file: %s', $localFilePath));
            }
            $this->musicallFilesystem->write($fullPath, $contents);

            $fileInfo[] = [$fileName, $this->musicallFilesystem->fileSize($fullPath)];
        }
        $this->addToPool(self::RANDOM_COURSE_COVER, $fileInfo);
    }
}

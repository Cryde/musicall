<?php

namespace App\Fixtures\Course;

use App\Service\File\RemoteFileDownloader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class FakeCourseImageGeneratorStory extends Story
{
    const string RANDOM_COURSE_COVER = 'random_course_cover';

    public function __construct(
        private readonly ParameterBagInterface $containerBag,
        private readonly RemoteFileDownloader  $remoteFileDownloader,
    ) {
    }

    public function build(): void
    {
        $fileInfo = [];
        for ($i = 0; $i < 30; $i++) {
            $fileInfo[] = $this->remoteFileDownloader->download('https://picsum.photos/seed/picsum_' . $i . '/400/400', $this->containerBag->get('file_publication_cover_destination'));
        }
        $this->addToPool(self::RANDOM_COURSE_COVER, $fileInfo);
    }
}

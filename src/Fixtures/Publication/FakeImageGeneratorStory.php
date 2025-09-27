<?php

namespace App\Fixtures\Publication;

use App\Service\File\RemoteFileDownloader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Zenstruck\Foundry\Story;

/** @codeCoverageIgnore */
class FakeImageGeneratorStory extends Story
{
    const string RANDOM_PUBLICATION_COVER = 'random_publication_cover';

    public function __construct(
        private readonly ParameterBagInterface $containerBag,
        private readonly RemoteFileDownloader  $remoteFileDownloader,
    ) {
    }

    public function build(): void
    {
        $fileInfo = [];
        for ($i = 0; $i < 30; $i++) {
            $fileInfo[] = $this->remoteFileDownloader->download('https://picsum.photos/seed/picsum'.$i.'/400/400', $this->containerBag->get('file_publication_cover_destination'));
        }
        $this->addToPool(self::RANDOM_PUBLICATION_COVER, $fileInfo);
    }
}

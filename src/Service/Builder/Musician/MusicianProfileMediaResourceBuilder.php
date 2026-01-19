<?php

namespace App\Service\Builder\Musician;

use App\ApiResource\Musician\MusicianProfileMedia;
use App\Entity\Musician\MusicianProfileMedia as MusicianProfileMediaEntity;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class MusicianProfileMediaResourceBuilder
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private CacheManager          $cacheManager,
    ) {
    }

    /**
     * @param MusicianProfileMediaEntity[] $mediaList
     *
     * @return MusicianProfileMedia[]
     */
    public function buildList(array $mediaList): array
    {
        return array_map($this->buildFromEntity(...), $mediaList);
    }

    public function buildFromEntity(MusicianProfileMediaEntity $media): MusicianProfileMedia
    {
        $profileMedia = new MusicianProfileMedia();
        $profileMedia->id = $media->getId();
        $profileMedia->platform = $media->getPlatform()->value;
        $profileMedia->platformLabel = $media->getPlatform()->getLabel();
        $profileMedia->url = $media->getUrl();
        $profileMedia->embedId = $media->getEmbedId();
        $profileMedia->title = $media->getTitle();
        $profileMedia->thumbnailUrl = $this->getThumbnailUrl($media);
        $profileMedia->position = $media->getPosition();

        return $profileMedia;
    }

    private function getThumbnailUrl(MusicianProfileMediaEntity $media): ?string
    {
        $thumbnailImageName = $media->getThumbnailImageName();
        if (!$thumbnailImageName) {
            return null;
        }

        /** @var string $destination */
        $destination = $this->parameterBag->get('file_musician_media_thumbnail_destination');
        $path = $destination . '/' . $thumbnailImageName;

        return $this->cacheManager->getBrowserPath($path, 'musician_media_thumbnail');
    }
}

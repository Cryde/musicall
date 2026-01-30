<?php

declare(strict_types=1);

namespace App\Service\Builder\Teacher;

use App\ApiResource\Teacher\TeacherProfileMedia;
use App\Entity\Teacher\TeacherProfileMedia as TeacherProfileMediaEntity;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

readonly class TeacherProfileMediaResourceBuilder
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private CacheManager $cacheManager,
    ) {
    }

    /**
     * @param TeacherProfileMediaEntity[] $mediaList
     *
     * @return TeacherProfileMedia[]
     */
    public function buildList(array $mediaList): array
    {
        return array_map($this->buildFromEntity(...), $mediaList);
    }

    public function buildFromEntity(TeacherProfileMediaEntity $media): TeacherProfileMedia
    {
        $profileMedia = new TeacherProfileMedia();
        $profileMedia->id = $media->getId();
        $profileMedia->platform = $media->getPlatform()->value;
        $profileMedia->url = $media->getUrl();
        $profileMedia->embedId = $media->getEmbedId();
        $profileMedia->title = $media->getTitle();
        $profileMedia->thumbnailUrl = $this->getThumbnailUrl($media);
        $profileMedia->position = $media->getPosition();

        return $profileMedia;
    }

    private function getThumbnailUrl(TeacherProfileMediaEntity $media): ?string
    {
        $thumbnailImageName = $media->getThumbnailImageName();
        if (!$thumbnailImageName) {
            return null;
        }

        // Reuse musician media thumbnail storage for now
        /** @var string $destination */
        $destination = $this->parameterBag->get('file_musician_media_thumbnail_destination');
        $path = $destination . '/' . $thumbnailImageName;

        return $this->cacheManager->getBrowserPath($path, 'musician_media_thumbnail');
    }
}

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
        /** @var string|null $mediaId */
        $mediaId = $media->id;
        $profileMedia->id = $mediaId;
        $profileMedia->platform = $media->platform->value;
        $profileMedia->url = $media->url;
        $profileMedia->embedId = $media->embedId;
        $profileMedia->title = $media->title;
        $profileMedia->thumbnailUrl = $this->getThumbnailUrl($media);
        $profileMedia->position = $media->position;

        return $profileMedia;
    }

    private function getThumbnailUrl(TeacherProfileMediaEntity $media): ?string
    {
        $thumbnailImageName = $media->thumbnailImageName;
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

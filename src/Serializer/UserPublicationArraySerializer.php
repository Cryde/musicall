<?php

namespace App\Serializer;

use App\Entity\Publication;
use App\Service\DatetimeHelper;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserPublicationArraySerializer
{
    public function __construct(private readonly UploaderHelper $uploaderHelper, private readonly CacheManager $cacheManager)
    {
    }

    /**
     * @param array|Publication[] $publications
     */
    public function listToArray(array $publications): array
    {
        $data = [];
        foreach ($publications as $publication) {
            $data[] = $this->toArray($publication);
        }

        return $data;
    }

    public function toArray(Publication $publication, bool $withContent = false): array
    {
        $cover = '';
        if ($publication->getCover()) {
            $path = $this->uploaderHelper->asset($publication->getCover(), 'imageFile');
            $cover = $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');
        }
        $result = [
            'id'                => $publication->getId(),
            'title'             => $publication->getTitle(),
            'slug'              => $publication->getSlug(),
            'creation_datetime' => $publication->getCreationDatetime()->format(DatetimeHelper::FORMAT_ISO_8601),
            'edition_datetime'  => $publication->getEditionDatetime() ? $publication->getEditionDatetime()->format(DatetimeHelper::FORMAT_ISO_8601) : null,
            'status_label'      => Publication::STATUS_LABEL[$publication->getStatus()],
            'status_id'         => $publication->getStatus(),
            'short_description' => $publication->getShortDescription(),
            'cover'             => $cover,
        ];
        if ($withContent) {
            $result['content'] = $publication->getContent();
        }

        return $result;
    }
}

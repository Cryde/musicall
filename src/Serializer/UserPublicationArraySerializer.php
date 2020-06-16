<?php

namespace App\Serializer;

use App\Entity\Publication;
use App\Service\DatetimeHelper;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserPublicationArraySerializer
{
    /**
     * @var UploaderHelper
     */
    private $uploaderHelper;
    /**
     * @var CacheManager
     */
    private $cacheManager;

    public function __construct(UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param array|Publication[] $publications
     *
     * @return array
     */
    public function listToArray(array $publications): array
    {
        $data = [];
        foreach ($publications as $publication) {
            $data[] = $this->toArray($publication);
        }

        return $data;
    }

    /**
     * @param Publication $publication
     * @param bool        $withContent
     *
     * @return array
     */
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
            'creation_datetime' => $publication->getCreationDatetime()->format(DatetimeHelper::FORMAT_DATETIME),
            'edition_datetime'  => $publication->getEditionDatetime() ? $publication->getEditionDatetime()->format(DatetimeHelper::FORMAT_DATETIME) : null,
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

<?php

namespace App\Serializer;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationSerializer
{
    /**
     * @var UploaderHelper
     */
    private $uploaderHelper;
    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

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
        $result = [];
        foreach ($publications as $publication) {
            $result[] = $this->toArray($publication);
        }

        return $result;
    }

    public function toArray(Publication $publication): array
    {
        $isVideo = $publication->getType() === Publication::TYPE_VIDEO;
        $description = $isVideo ? '' : $publication->getShortDescription();

        $path = $this->uploaderHelper->asset($publication->getCover(), 'imageFile');
        $cover = $this->cacheManager->getBrowserPath($path, 'publication_cover_300x300');

        return [
            'slug'                 => $publication->getSlug(),
            'type'                 => $isVideo ? 'video' : 'text',
            'category_type'        => $publication->getSubCategory()->getType() === PublicationSubCategory::TYPE_PUBLICATION ? 'publication' : 'course',
            'category'             => $publication->getSubCategory()->getSlug(),
            'title'                => mb_strtoupper($publication->getTitle()),
            'description'          => $description,
            'publication_datetime' => $publication->getPublicationDatetime(),
            'cover_image'          => $cover,
            'author_username'      => $publication->getAuthor()->getUsername(),
        ];
    }
}

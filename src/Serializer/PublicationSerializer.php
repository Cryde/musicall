<?php

namespace App\Serializer;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Serializer\Publication\SubCategoryArraySerializer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationSerializer
{
    private UploaderHelper $uploaderHelper;
    private CacheManager $cacheManager;
    private SubCategoryArraySerializer $subCategoryArraySerializer;

    public function __construct(
        UploaderHelper $uploaderHelper,
        CacheManager $cacheManager,
        SubCategoryArraySerializer $subCategoryArraySerializer
    )
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->cacheManager = $cacheManager;
        $this->subCategoryArraySerializer = $subCategoryArraySerializer;
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
            'category'             => $this->subCategoryArraySerializer->toArray($publication->getSubCategory()),
            'is_course'            => $publication->getSubCategory()->getType() === PublicationSubCategory::TYPE_COURSE,
            'title'                => mb_strtoupper($publication->getTitle()),
            'description'          => $description,
            'publication_datetime' => $publication->getPublicationDatetime(),
            'cover_image'          => $cover,
            'author_username'      => $publication->getAuthor()->getUsername(),
            'comments_number'      => $publication->getThread()->getCommentNumber(),
        ];
    }
}

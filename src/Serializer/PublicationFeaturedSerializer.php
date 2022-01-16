<?php

namespace App\Serializer;

use App\Entity\Image\GalleryImage;
use App\Entity\PublicationFeatured;
use Doctrine\Common\Collections\Collection;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationFeaturedSerializer
{
    private UploaderHelper $uploaderHelper;
    private CacheManager $cacheManager;

    public function __construct(UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        $this->uploaderHelper = $uploaderHelper;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @param Collection|PublicationFeatured[] $publicationFeatured
     *
     * @return array
     */
    public function toList($publicationFeatured): array
    {
        $list = [];
        foreach ($publicationFeatured as $featured) {
            $list[] = $this->toArray($featured);
        }

        return $list;
    }

    public function toArray(PublicationFeatured $publicationFeatured): array
    {
        $imagePath = $publicationFeatured->getCover() ? $this->uploaderHelper->asset($publicationFeatured->getCover(), 'imageFile') : '';
        $publication = $publicationFeatured->getPublication();

        return [
            'id'    => $publicationFeatured->getId(),
            'title' => $publicationFeatured->getTitle(),
            'description' => $publicationFeatured->getDescription(),
            'publication' => ['slug' => $publication->getSlug()],
            'level' => $publicationFeatured->getLevel(),
            'status' => $publicationFeatured->getStatus(),
            'options' => $publicationFeatured->getOptions(),
            'cover' => $imagePath ? $this->cacheManager->getBrowserPath($imagePath, 'featured_cover_filter') : '',
        ];
    }
}

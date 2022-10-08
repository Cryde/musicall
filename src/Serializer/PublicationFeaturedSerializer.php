<?php

namespace App\Serializer;

use App\Entity\Image\GalleryImage;
use App\Entity\PublicationFeatured;
use Doctrine\Common\Collections\Collection;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationFeaturedSerializer
{
    public function __construct(private readonly UploaderHelper $uploaderHelper, private readonly CacheManager $cacheManager)
    {
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

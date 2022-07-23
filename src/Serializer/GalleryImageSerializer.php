<?php

namespace App\Serializer;

use App\Entity\Image\GalleryImage;
use Doctrine\Common\Collections\Collection;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class GalleryImageSerializer
{
    public function __construct(private readonly UploaderHelper $uploaderHelper, private readonly CacheManager $cacheManager)
    {
    }

    /**
     * @param Collection|GalleryImage[] $images
     */
    public function toList(\Doctrine\Common\Collections\Collection|array $images): array
    {
        $list = [];
        foreach ($images as $image) {
            $list[] = $this->toArray($image);
        }

        return $list;
    }

    public function toArray(GalleryImage $galleryImage): array
    {
        $imagePath = $this->uploaderHelper->asset($galleryImage, 'imageFile');

        return [
            'id'    => $galleryImage->getId(),
            'sizes' => [
                'small'  => $this->cacheManager->getBrowserPath($imagePath, 'gallery_image_filter_small'),
                'medium' => $this->cacheManager->getBrowserPath($imagePath, 'gallery_image_filter_medium'),
                'full'   => $this->cacheManager->getBrowserPath($imagePath, 'gallery_image_filter_full'),
            ],
        ];
    }
}

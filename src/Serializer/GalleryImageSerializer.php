<?php

namespace App\Serializer;

use App\Entity\Image\GalleryImage;
use Doctrine\Common\Collections\Collection;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class GalleryImageSerializer
{
    /**
     * @var UploaderHelper
     */
    private UploaderHelper $uploaderHelper;
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
     * @param Collection|GalleryImage[] $images
     *
     * @return array
     */
    public function toList(Collection $images): array
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
                'small'  => $this->cacheManager->generateUrl($imagePath, 'gallery_image_filter_small'),
                'medium' => $this->cacheManager->generateUrl($imagePath, 'gallery_image_filter_medium'),
                'full'   => $this->cacheManager->generateUrl($imagePath, 'gallery_image_filter_full'),
            ],
        ];
    }
}

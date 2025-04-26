<?php

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\Gallery;
use App\ApiResource\Publication\GalleryImage as GalleryImageModel;
use App\ApiResource\Publication\Publication;
use App\Entity\Gallery as GalleryEntity;
use App\Entity\Image\GalleryImage;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class GalleryImageBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager   $cacheManager,
    ) {
    }

    /**
     * @param GalleryImage[] $galleryImages
     *
     * @return GalleryImageModel[]
     */
    public function buildFromEntities(Collection|array $galleryImages): array
    {
        $images = [];
        foreach ($galleryImages as $galleryImage) {
            $images[] = $this->buildImage($galleryImage);
        }

        return $images;
    }

    public function buildImage(GalleryImage $galleryImage): GalleryImageModel
    {
        $imageModel = new GalleryImageModel();
        $imageModel->id = $galleryImage->getId() ?: 0;
        $imageModel->format = $this->buildFormat($galleryImage);

        return $imageModel;
    }

    private function buildFormat(GalleryImage $galleryImage): Gallery\Format
    {
        $imagePath = $this->uploaderHelper->asset($galleryImage, 'imageFile');
        $format = new Gallery\Format();
        $format->small = $this->cacheManager->getBrowserPath($imagePath, 'gallery_image_filter_small');
        $format->medium = $this->cacheManager->getBrowserPath($imagePath, 'gallery_image_filter_medium');
        $format->full = $this->cacheManager->getBrowserPath($imagePath, 'gallery_image_filter_full');

        return $format;
    }
}
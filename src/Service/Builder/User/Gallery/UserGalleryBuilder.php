<?php declare(strict_types=1);

namespace App\Service\Builder\User\Gallery;

use App\ApiResource\User\Gallery\UserGallery;
use App\ApiResource\User\Gallery\UserGalleryEdit;
use App\ApiResource\User\Gallery\UserGalleryImage;
use App\ApiResource\User\Gallery\UserGalleryPreview;
use App\Entity\Gallery;
use App\Entity\Image\GalleryImage;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class UserGalleryBuilder
{
    private const STATUS_LABELS = [
        Gallery::STATUS_ONLINE => 'En ligne',
        Gallery::STATUS_DRAFT => 'Brouillon',
        Gallery::STATUS_PENDING => 'En validation',
    ];

    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function buildFromEntity(Gallery $gallery): UserGallery
    {
        $dto = new UserGallery();
        $dto->id = $gallery->getId();
        $dto->title = $gallery->getTitle();
        $dto->slug = $gallery->getSlug();
        $dto->description = $gallery->getDescription();
        $dto->creationDatetime = $gallery->getCreationDatetime();
        $dto->status = $gallery->getStatus();
        $dto->statusLabel = self::STATUS_LABELS[$gallery->getStatus()] ?? 'Inconnu';
        $dto->imageCount = $gallery->getImageCount();
        $dto->coverImageUrl = $this->getCoverImageUrl($gallery);

        return $dto;
    }

    public function buildEditFromEntity(Gallery $gallery): UserGalleryEdit
    {
        $dto = new UserGalleryEdit();
        $dto->id = $gallery->getId();
        $dto->title = $gallery->getTitle();
        $dto->description = $gallery->getDescription();
        $dto->status = $gallery->getStatus();
        $dto->coverImageUrl = $this->getCoverImageUrl($gallery);
        $dto->coverImageId = $gallery->getCoverImage()?->getId();

        return $dto;
    }

    public function buildPreviewFromEntity(Gallery $gallery): UserGalleryPreview
    {
        $dto = new UserGalleryPreview();
        $dto->id = $gallery->getId();
        $dto->title = $gallery->getTitle();
        $dto->slug = $gallery->getSlug();
        $dto->description = $gallery->getDescription();
        $dto->status = $gallery->getStatus();
        $dto->statusLabel = self::STATUS_LABELS[$gallery->getStatus()] ?? 'Inconnu';
        $dto->creationDatetime = $gallery->getCreationDatetime();
        $dto->authorUsername = $gallery->getAuthor()->getUsername();
        $dto->images = array_map(
            fn (GalleryImage $image) => $this->getImageSizes($image),
            $gallery->getImages()->toArray()
        );

        return $dto;
    }

    public function buildImageFromEntity(GalleryImage $image): UserGalleryImage
    {
        $dto = new UserGalleryImage();
        $dto->id = $image->getId();
        $dto->sizes = $this->getImageSizes($image);

        return $dto;
    }

    private function getCoverImageUrl(Gallery $gallery): ?string
    {
        $coverImage = $gallery->getCoverImage();
        if (!$coverImage) {
            return null;
        }

        $path = $this->uploaderHelper->asset($coverImage, 'imageFile');
        return $path ? $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium') : null;
    }

    private function getImageSizes(GalleryImage $image): array
    {
        $path = $this->uploaderHelper->asset($image, 'imageFile');
        if (!$path) {
            return [];
        }

        return [
            'small' => $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_small'),
            'medium' => $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium'),
            'full' => $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_full'),
        ];
    }
}

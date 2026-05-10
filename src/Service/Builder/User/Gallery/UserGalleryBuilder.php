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
    /** @var array<int, string> */
    private const array STATUS_LABELS = [
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
        $creationDatetime = $gallery->creationDatetime;

        $dto = new UserGallery();
        $dto->id = (int) $gallery->id;
        $dto->title = $gallery->title;
        $dto->slug = $gallery->slug;
        $dto->description = $gallery->description;
        $dto->creationDatetime = $creationDatetime;
        $dto->status = $gallery->status;
        $dto->statusLabel = self::STATUS_LABELS[$gallery->status] ?? 'Inconnu';
        $dto->imageCount = $gallery->getImageCount();
        $dto->coverImageUrl = $this->getCoverImageUrl($gallery);

        return $dto;
    }

    public function buildEditFromEntity(Gallery $gallery): UserGalleryEdit
    {
        $dto = new UserGalleryEdit();
        $dto->id = (int) $gallery->id;
        $dto->title = $gallery->title;
        $dto->description = $gallery->description;
        $dto->status = $gallery->status;
        $dto->coverImageUrl = $this->getCoverImageUrl($gallery);
        $dto->coverImageId = $gallery->coverImage?->id;

        return $dto;
    }

    public function buildPreviewFromEntity(Gallery $gallery): UserGalleryPreview
    {
        $author = $gallery->author;
        $creationDatetime = $gallery->creationDatetime;

        $dto = new UserGalleryPreview();
        $dto->id = (int) $gallery->id;
        $dto->title = $gallery->title;
        $dto->slug = (string) $gallery->slug;
        $dto->description = $gallery->description;
        $dto->status = $gallery->status;
        $dto->statusLabel = self::STATUS_LABELS[$gallery->status] ?? 'Inconnu';
        $dto->creationDatetime = $creationDatetime;
        $dto->authorUsername = $author->username;
        $dto->images = array_map(
            fn (GalleryImage $image): array => $this->getImageSizes($image),
            $gallery->images->toArray()
        );

        return $dto;
    }

    public function buildImageFromEntity(GalleryImage $image): UserGalleryImage
    {
        $dto = new UserGalleryImage();
        $dto->id = (int) $image->id;
        $dto->sizes = $this->getImageSizes($image);

        return $dto;
    }

    private function getCoverImageUrl(Gallery $gallery): ?string
    {
        $coverImage = $gallery->coverImage;
        if (!$coverImage instanceof \App\Entity\Image\GalleryImage) {
            return null;
        }

        $path = $this->uploaderHelper->asset($coverImage, 'imageFile');
        return $path ? $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium') : null;
    }

    /**
     * @return array{small?: string, medium?: string, full?: string}
     */
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

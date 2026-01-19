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
        $creationDatetime = $gallery->getCreationDatetime();

        $dto = new UserGallery();
        $dto->id = (int) $gallery->getId();
        $dto->title = (string) $gallery->getTitle();
        $dto->slug = $gallery->getSlug();
        $dto->description = $gallery->getDescription();
        $dto->creationDatetime = $creationDatetime;
        $dto->status = (int) $gallery->getStatus();
        $dto->statusLabel = self::STATUS_LABELS[$gallery->getStatus()] ?? 'Inconnu';
        $dto->imageCount = $gallery->getImageCount();
        $dto->coverImageUrl = $this->getCoverImageUrl($gallery);

        return $dto;
    }

    public function buildEditFromEntity(Gallery $gallery): UserGalleryEdit
    {
        $dto = new UserGalleryEdit();
        $dto->id = (int) $gallery->getId();
        $dto->title = (string) $gallery->getTitle();
        $dto->description = $gallery->getDescription();
        $dto->status = (int) $gallery->getStatus();
        $dto->coverImageUrl = $this->getCoverImageUrl($gallery);
        $dto->coverImageId = $gallery->getCoverImage()?->getId();

        return $dto;
    }

    public function buildPreviewFromEntity(Gallery $gallery): UserGalleryPreview
    {
        $author = $gallery->getAuthor();
        $creationDatetime = $gallery->getCreationDatetime();

        $dto = new UserGalleryPreview();
        $dto->id = (int) $gallery->getId();
        $dto->title = (string) $gallery->getTitle();
        $dto->slug = (string) $gallery->getSlug();
        $dto->description = $gallery->getDescription();
        $dto->status = (int) $gallery->getStatus();
        $dto->statusLabel = self::STATUS_LABELS[$gallery->getStatus()] ?? 'Inconnu';
        $dto->creationDatetime = $creationDatetime;
        $dto->authorUsername = $author->getUsername();
        $dto->images = array_map(
            fn (GalleryImage $image) => $this->getImageSizes($image),
            $gallery->getImages()->toArray()
        );

        return $dto;
    }

    public function buildImageFromEntity(GalleryImage $image): UserGalleryImage
    {
        $dto = new UserGalleryImage();
        $dto->id = (int) $image->getId();
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

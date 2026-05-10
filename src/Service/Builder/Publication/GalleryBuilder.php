<?php declare(strict_types=1);

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\GalleryImage;
use App\ApiResource\Publication\GalleryResource;
use App\ApiResource\Publication\Publication\Author;
use App\ApiResource\Publication\Publication\Cover;
use App\ApiResource\Publication\Publication\Category;
use App\ApiResource\Publication\Gallery;
use App\Entity\Gallery as GalleryEntity;
use App\Entity\Image\GalleryImage as GalleryImageEntity;
use App\Entity\User;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class GalleryBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager   $cacheManager,
    ) {
    }

    public function buildResource(GalleryEntity $entity): GalleryResource
    {
        $dto = new GalleryResource();
        $dto->id = (int) $entity->id;
        $dto->title = $entity->title;
        $dto->publicationDatetime = $entity->publicationDatetime;
        $dto->author = $entity->author;
        $dto->coverImage = $this->buildCoverImageUrl($entity->coverImage);
        $dto->slug = $entity->slug;
        $dto->imageCount = $entity->getImageCount();

        return $dto;
    }

    private function buildCoverImageUrl(?GalleryImageEntity $coverImage): ?string
    {
        if (!$coverImage instanceof GalleryImageEntity) {
            return null;
        }

        $path = $this->uploaderHelper->asset($coverImage, 'imageFile');

        return $path ? $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium') : null;
    }

    public function buildFromEntity(GalleryEntity $galleryEntity): Gallery
    {
        $author = $galleryEntity->author;
        $coverImage = $galleryEntity->coverImage;
        $publicationDatetime = $galleryEntity->publicationDatetime;
        assert($coverImage instanceof GalleryImageEntity && $publicationDatetime instanceof \DateTimeInterface);

        $gallery = new Gallery();
        $gallery->slug = (string) $galleryEntity->slug;
        $gallery->title = $galleryEntity->title;
        $gallery->description = $galleryEntity->description ?? '';
        $gallery->publicationDatetime = $publicationDatetime;
        $gallery->author = $this->buildAuthor($author);
        $gallery->cover = $this->buildCover($coverImage);
        $gallery->category = $this->buildCategory();
        $gallery->images = $this->buildImagesFromEntity($galleryEntity);

        return $gallery;
    }

    private function buildAuthor(User $user): Author
    {
        $author = new Author();
        $author->username = $user->username;
        $author->deletionDatetime = $user->deletionDatetime;

        return $author;
    }

    private function buildCover(GalleryImageEntity $galleryCover): Cover
    {
        $path = $this->uploaderHelper->asset($galleryCover, 'imageFile');
        assert($path !== null);
        $cover = new Cover();
        $cover->coverUrl = $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium');

        return $cover;
    }

    /**
     * @return GalleryImage[]
     */
    public function buildImagesFromEntity(GalleryEntity $gallery): array
    {
        return array_map(
            fn (GalleryImageEntity $image): GalleryImage => $this->buildImageFromEntity($image),
            $gallery->images->toArray()
        );
    }

    public function buildImageFromEntity(GalleryImageEntity $image): GalleryImage
    {
        $dto = new GalleryImage();
        $dto->sizes = $this->getImageSizes($image);

        return $dto;
    }

    /**
     * @return array{small?: string, medium?: string, full?: string}
     */
    private function getImageSizes(GalleryImageEntity $image): array
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

    private function buildCategory(): Category
    {
        $category = new Category();
        $category->id = 0;
        $category->slug = 'gallery';
        $category->title = 'gallery';

        return $category;
    }
}

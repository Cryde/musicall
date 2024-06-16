<?php

namespace App\Service\Builder\Publication;

use App\ApiResource\Publication\Gallery;
use App\ApiResource\Publication\Publication;
use App\Entity\Gallery as GalleryEntity;
use App\Entity\Image\GalleryImage;
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

    public function buildFromEntity(GalleryEntity $galleryEntity): Gallery
    {
        $gallery = new Gallery();
        $gallery->slug = $galleryEntity->getSlug();
        $gallery->title = $galleryEntity->getTitle();
        $gallery->description = $galleryEntity->getDescription() ?? '';
        $gallery->publicationDatetime = $galleryEntity->getPublicationDatetime();
        $gallery->author = $this->buildAuthor($galleryEntity->getAuthor());
        $gallery->cover = $this->buildCover($galleryEntity->getCoverImage());
        $gallery->category = $this->buildCategory();

        return $gallery;
    }

    private function buildAuthor(User $user): Publication\Author
    {
        $author = new Publication\Author();
        $author->username = $user->getUsername();

        return $author;
    }

    private function buildCover(GalleryImage $galleryCover): Publication\Cover
    {
        $path = $this->uploaderHelper->asset($galleryCover, 'imageFile');
        $cover = new Publication\Cover();
        $cover->coverUrl = $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium');

        return $cover;
    }

    private function buildCategory(): Publication\Category
    {
        $category = new Publication\Category();
        $category->id = 0;
        $category->slug = 'gallery';
        $category->title = 'gallery';

        return $category;
    }
}
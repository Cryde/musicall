<?php

namespace App\Service\Builder\ElasticSearch\Publication;

use App\ElasticSearch\Publication\Author;
use App\ElasticSearch\Publication\Category;
use App\ElasticSearch\Publication\Media;
use App\ElasticSearch\Publication\Publication;
use App\Entity\Gallery;
use App\Enum\Publication\PublicationType;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationBuilder
{
    public function __construct(
        private readonly UploaderHelper $uploaderHelper,
        private readonly CacheManager   $cacheManager
    ) {
    }

    public function buildFromGallery(Gallery $gallery): Publication
    {
        $path = $this->uploaderHelper->asset($gallery->getCoverImage(), 'imageFile');
        $cover = $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium');

        $publication = new Publication();
        $publication->id = (string)$gallery->getId();
        $publication->title = $gallery->getTitle();
        $publication->publicationType = PublicationType::Gallery;
        $publication->slug = $gallery->getSlug();
        $publication->isVideo = false;
        $publication->content = $gallery->getDescription() ?? '';
        $publication->shortDescription = $gallery->getDescription() ?? '';
        $publication->publicationDatetime = $gallery->getPublicationDatetime();
        $publication->media = $this->buildMedia($cover);
        $publication->author = $this->buildAuthor($gallery->getAuthor()?->getId() ?? '');
        $publication->category = $this->buildCategory('gallery', 'gallery');

        return $publication;
    }

    private function buildAuthor(string $id): Author
    {
        $author = new Author();
        $author->id = $id;

        return $author;
    }

    private function buildMedia(string $coverImageUrl): Media
    {
        $media = new Media();
        $media->coverImageUrl = $coverImageUrl;

        return $media;
    }

    private function buildCategory(string $id, string $label): Category
    {
        $category = new Category();
        $category->id = $id;
        $category->label = $label;

        return $category;
    }
}
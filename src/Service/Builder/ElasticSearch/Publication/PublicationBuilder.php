<?php

namespace App\Service\Builder\ElasticSearch\Publication;

use App\ElasticSearch\Publication\Author;
use App\ElasticSearch\Publication\Category;
use App\ElasticSearch\Publication\Media;
use App\ElasticSearch\Publication\Publication;
use App\Entity\Gallery;
use App\Entity\Publication as PublicationEntity;
use App\Enum\Publication\PublicationCategoryType;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationBuilder
{
    public function __construct(
        private readonly UploaderHelper $uploaderHelper,
        private readonly CacheManager   $cacheManager,
        private readonly HtmlSanitizerInterface $appPublicationSanitizer
    ) {
    }

    public function buildFromGallery(Gallery $gallery): Publication
    {
        $path = $this->uploaderHelper->asset($gallery->getCoverImage(), 'imageFile');
        $cover = $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium');

        $publication = new Publication();
        $publication->id = (string)$gallery->getId();
        $publication->title = $gallery->getTitle();
        $publication->publicationType = PublicationCategoryType::Gallery;
        $publication->slug = $gallery->getSlug();
        $publication->isVideo = false;
        $publication->content = $gallery->getDescription() ?? '';
        $publication->shortDescription = $gallery->getDescription() ?? '';
        $publication->publicationDatetime = $gallery->getPublicationDatetime();
        $publication->media = $this->buildMedia($cover);
        $publication->author = $this->buildAuthor($gallery->getAuthor()?->getId() ?? '');
        $publication->category = $this->buildCategory('gallery', 'Gallery', 'gallery');

        return $publication;
    }

    public function buildFromPublication(PublicationEntity $entityPublication): Publication
    {
        $path = $this->uploaderHelper->asset($entityPublication->getCover(), 'imageFile');
        $cover = $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_medium');

        $publication = new Publication();
        $publication->id = (string)$entityPublication->getId();
        $publication->title = $entityPublication->getTitle();
        $publication->publicationType = PublicationCategoryType::Publication;
        $publication->slug = $entityPublication->getSlug();
        $publication->isVideo = $entityPublication->getType() === PublicationEntity::TYPE_VIDEO;
        $publication->content = $entityPublication->getDescription() ?? '';
        $publication->shortDescription = $entityPublication->getDescription() ?? '';
        $publication->publicationDatetime = $entityPublication->getPublicationDatetime();
        $publication->media = $this->buildMedia($cover);
        $publication->author = $this->buildAuthor($entityPublication->getAuthor()?->getId() ?? '');
        $publication->category = $this->buildCategory(
            $entityPublication->getSubCategory()->getId(),
            $entityPublication->getSubCategory()->getTitle(),
            $entityPublication->getSubCategory()->getSlug()
        );

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

    private function buildCategory(string $id, string $label, string $slug): Category
    {
        $category = new Category();
        $category->id = $id;
        $category->label = $label;
        $category->slug = $slug;

        return $category;
    }
}
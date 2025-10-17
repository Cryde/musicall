<?php declare(strict_types=1);

namespace App\Service\Bot;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Repository\GalleryRepository;
use App\Repository\PublicationRepository;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class BotMetaDataGenerator
{
    public function __construct(
        private readonly PublicationRepository $publicationRepository,
        private readonly UploaderHelper        $uploaderHelper,
        private readonly CacheManager          $cacheManager,
        private readonly GalleryRepository     $galleryRepository
    ) {
    }

    public function getMetaData(string $uri): array
    {
        if (preg_match('#/publications/(.+)#', $uri, $matches)) {
            $slug = $matches[1];

            return $this->getForPublications($slug);
        }
        if (preg_match('#/gallery/(.+)#', $uri, $matches)) {
            $slug = $matches[1];

            return $this->getForGallery($slug);
        }

        return [];
    }

    private function getForPublications(string $slug): array
    {
        $publication = $this->publicationRepository->findOneBy(['slug' => $slug, 'status' => Publication::STATUS_ONLINE]);
        if (!$publication) {
            return [];
        }

        $path = $this->uploaderHelper->asset($publication->getCover(), 'imageFile');
        $cover = $this->cacheManager->getBrowserPath($path, 'publication_image_filter');
        return [
            'title'       => $publication->getTitle(),
            'description' => $publication->getShortDescription(),
            'cover'       => $cover,
        ];
    }

    public function getForGallery(string $slug): array
    {
        $gallery = $this->galleryRepository->findOneBy(['slug' => $slug, 'status' => Gallery::STATUS_ONLINE]);
        if (!$gallery) {
            return [];
        }

        $path = $this->uploaderHelper->asset($gallery->getCoverImage(), 'imageFile');
        $cover = $this->cacheManager->getBrowserPath($path, 'gallery_image_filter_full');
        return [
            'title'       => $gallery->getTitle(),
            'description' => $gallery->getDescription(),
            'cover'       => $cover,
        ];
    }
}

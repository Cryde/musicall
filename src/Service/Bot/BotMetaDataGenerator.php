<?php

namespace App\Service\Bot;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Repository\GalleryRepository;
use App\Repository\PublicationRepository;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class BotMetaDataGenerator
{
    public function __construct(
        private readonly PublicationRepository $publicationRepository,
        private readonly UploaderHelper        $uploaderHelper,
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

        return [
            'title'       => $publication->getTitle(),
            'description' => $publication->getShortDescription(),
            'cover'       => $this->uploaderHelper->asset($publication->getCover(), 'imageFile'),
        ];
    }

    public function getForGallery(string $slug): array
    {
        $gallery = $this->galleryRepository->findOneBy(['slug' => $slug, 'status' => Gallery::STATUS_ONLINE]);

        if (!$gallery) {
            return [];
        }

        return [
            'title'       => $gallery->getTitle(),
            'description' => $gallery->getDescription(),
            'cover'       => $this->uploaderHelper->asset($gallery->getCoverImage(), 'imageFile'),
        ];
    }
}

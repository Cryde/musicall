<?php

namespace App\Service\Bot;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class BotMetaDataGenerator
{
    /**
     * @var PublicationRepository
     */
    private PublicationRepository $publicationRepository;
    /**
     * @var UploaderHelper
     */
    private UploaderHelper $uploaderHelper;

    public function __construct(PublicationRepository $publicationRepository, UploaderHelper $uploaderHelper)
    {
        $this->publicationRepository = $publicationRepository;
        $this->uploaderHelper = $uploaderHelper;
    }

    public function getMetaData(string $uri): array
    {
        if (preg_match('#/publications/(.+)#', $uri, $matches)) {
            $slug = $matches[1];

            return $this->getForPublications($slug);
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
}

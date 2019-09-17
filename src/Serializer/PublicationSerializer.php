<?php

namespace App\Serializer;

use App\Entity\Publication;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class PublicationSerializer
{
    /**
     * @var UploaderHelper
     */
    private $uploaderHelper;

    public function __construct(UploaderHelper $uploaderHelper)
    {
        $this->uploaderHelper = $uploaderHelper;
    }

    /**
     * @param array|Publication[] $publications
     *
     * @return array
     */
    public function listToArray(array $publications): array
    {
        $result = [];
        foreach ($publications as $publication) {
            $result[] = $this->toArray($publication);
        }

        return $result;
    }

    public function toArray(Publication $publication): array
    {
        return [
            'slug'            => $publication->getSlug(),
            'title'           => $publication->getTitle(),
            'description'     => $publication->getShortDescription(),
            'cover_image'     => $this->uploaderHelper->asset($publication->getCover(), 'imageFile'),
            'author_username' => $publication->getAuthor()->getUsername(),
        ];
    }
}

<?php

namespace App\Serializer;

use App\Entity\Publication;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class SmallPublicationSerializer
{
    public function __construct(private readonly UploaderHelper $uploaderHelper)
    {
    }

    /**
     * @param array|Publication[] $publications
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
            'id'          => $publication->getId(),
            'title'       => mb_strtoupper((string) $publication->getTitle()),
            'description' => $publication->getShortDescription(),
            'cover_image' => $this->uploaderHelper->asset($publication->getCover(), 'imageFile'),
        ];
    }
}

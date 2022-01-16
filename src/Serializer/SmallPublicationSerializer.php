<?php

namespace App\Serializer;

use App\Entity\Publication;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class SmallPublicationSerializer
{
    private UploaderHelper $uploaderHelper;

    public function __construct(UploaderHelper $uploaderHelper)
    {
        $this->uploaderHelper = $uploaderHelper;
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
            'title'       => mb_strtoupper($publication->getTitle()),
            'description' => $publication->getShortDescription(),
            'cover_image' => $this->uploaderHelper->asset($publication->getCover(), 'imageFile'),
        ];
    }
}

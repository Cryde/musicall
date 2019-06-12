<?php

namespace App\Serializer;

use App\Entity\Publication;
use App\Service\DatetimeHelper;

class UserPublicationArraySerializer
{
    /**
     * @param array|Publication[] $publications
     *
     * @return array
     */
    public function listToArray(array $publications): array
    {
        $data = [];
        foreach ($publications as $publication) {
            $data[] = $this->toArray($publication);
        }

        return $data;
    }

    /**
     * @param Publication $publication
     *
     * @return array
     */
    public function toArray(Publication $publication): array
    {
        return [
            'id'                => $publication->getId(),
            'title'             => $publication->getTitle(),
            'slug'              => $publication->getSlug(),
            'creation_datetime' => $publication->getCreationDatetime()->format(DatetimeHelper::FORMAT_DATETIME),
            'edition_datetime'  => $publication->getEditionDatetime() ? $publication->getEditionDatetime()->format(DatetimeHelper::FORMAT_DATETIME) : null,
            'status'            => Publication::STATUS_LABEL[$publication->getStatus()],
        ];
    }
}

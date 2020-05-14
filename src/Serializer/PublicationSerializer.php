<?php

namespace App\Serializer;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
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
        $isVideo = $publication->getType() === Publication::TYPE_VIDEO;
        $description = $isVideo ? '' : $publication->getShortDescription();

        return [
            'slug'                 => $publication->getSlug(),
            'type'                 => $isVideo ? 'video' : 'text',
            'category_type'        => $publication->getSubCategory()->getType() === PublicationSubCategory::TYPE_PUBLICATION ? 'publication' : 'course',
            'category'             => $publication->getSubCategory()->getSlug(),
            'title'                => mb_strtoupper($publication->getTitle()),
            'description'          => $description,
            'publication_datetime' => $publication->getPublicationDatetime(),
            'cover_image'          => $this->uploaderHelper->asset($publication->getCover(), 'imageFile'),
            'author_username'      => $publication->getAuthor()->getUsername(),
        ];
    }
}

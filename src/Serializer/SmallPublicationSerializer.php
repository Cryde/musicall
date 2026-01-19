<?php declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Publication;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class SmallPublicationSerializer
{
    public function __construct(private readonly UploaderHelper $uploaderHelper)
    {
    }

    /**
     * @return array{id: ?int, title: string, description: ?string, cover_image: ?string}
     */
    public function toArray(Publication $publication): array
    {
        $cover = $publication->getCover();

        return [
            'id'          => $publication->getId(),
            'title'       => mb_strtoupper((string) $publication->getTitle()),
            'description' => $publication->getShortDescription(),
            'cover_image' => $cover ? $this->uploaderHelper->asset($cover, 'imageFile') : null,
        ];
    }
}

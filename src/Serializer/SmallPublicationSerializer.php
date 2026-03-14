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
        $cover = $publication->cover;

        return [
            'id'          => $publication->id,
            'title'       => mb_strtoupper((string) $publication->title),
            'description' => $publication->shortDescription,
            'cover_image' => $cover ? $this->uploaderHelper->asset($cover, 'imageFile') : null,
        ];
    }
}

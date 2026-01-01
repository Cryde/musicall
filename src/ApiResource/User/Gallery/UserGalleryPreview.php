<?php declare(strict_types=1);

namespace App\ApiResource\User\Gallery;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\User\Gallery\UserGalleryPreviewProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/user/galleries/{id}/preview',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_preview',
            provider: UserGalleryPreviewProvider::class,
        ),
    ]
)]
class UserGalleryPreview
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public string $title;

    public string $slug;

    public ?string $description = null;

    public int $status;

    public string $statusLabel;

    public \DateTimeInterface $creationDatetime;

    public string $authorUsername;

    public array $images = [];
}

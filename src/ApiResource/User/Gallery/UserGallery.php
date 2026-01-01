<?php declare(strict_types=1);

namespace App\ApiResource\User\Gallery;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Gallery\UserGalleryDeleteProcessor;
use App\State\Provider\User\Gallery\UserGalleryCollectionProvider;
use App\State\Provider\User\Gallery\UserGalleryDeleteProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/user/galleries',
            openapi: new Operation(tags: ['User Galleries']),
            paginationEnabled: false,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_list',
            provider: UserGalleryCollectionProvider::class,
        ),
        new Delete(
            uriTemplate: '/user/galleries/{id}',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_delete',
            provider: UserGalleryDeleteProvider::class,
            processor: UserGalleryDeleteProcessor::class,
        ),
    ]
)]
class UserGallery
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public string $title;

    public ?string $slug = null;

    public ?string $description = null;

    public \DateTimeInterface $creationDatetime;

    public int $status;

    public string $statusLabel;

    public int $imageCount;

    public ?string $coverImageUrl = null;
}

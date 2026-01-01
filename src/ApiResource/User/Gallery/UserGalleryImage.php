<?php declare(strict_types=1);

namespace App\ApiResource\User\Gallery;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Gallery\UserGalleryImageDeleteProcessor;
use App\State\Processor\User\Gallery\UserGalleryImageSetCoverProcessor;
use App\State\Provider\User\Gallery\UserGalleryImageCollectionProvider;
use App\State\Provider\User\Gallery\UserGalleryImageDeleteProvider;
use App\State\Provider\User\Gallery\UserGalleryImageSetCoverProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/user/galleries/{id}/images',
            openapi: new Operation(tags: ['User Galleries']),
            paginationEnabled: false,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_images',
            provider: UserGalleryImageCollectionProvider::class,
        ),
        new Patch(
            uriTemplate: '/user/gallery-images/{id}/cover',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_image_cover',
            provider: UserGalleryImageSetCoverProvider::class,
            processor: UserGalleryImageSetCoverProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/gallery-images/{id}',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_image_delete',
            provider: UserGalleryImageDeleteProvider::class,
            processor: UserGalleryImageDeleteProcessor::class,
        ),
    ]
)]
class UserGalleryImage
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public array $sizes = [];
}

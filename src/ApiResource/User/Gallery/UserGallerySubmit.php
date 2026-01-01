<?php declare(strict_types=1);

namespace App\ApiResource\User\Gallery;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Gallery\UserGallerySubmitProcessor;
use App\State\Provider\User\Gallery\UserGallerySubmitProvider;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/user/galleries/{id}/submit',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_validation',
            provider: UserGallerySubmitProvider::class,
            processor: UserGallerySubmitProcessor::class,
        ),
    ]
)]
class UserGallerySubmit
{
    #[ApiProperty(identifier: true)]
    public int $id;
}

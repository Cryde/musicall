<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Publication\UserPublicationUploadImageProcessor;
use App\State\Provider\User\Publication\UserPublicationEditProvider;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/user/publications/{id}/upload-image',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_upload_image',
            provider: UserPublicationEditProvider::class,
            processor: UserPublicationUploadImageProcessor::class,
            deserialize: false,
        ),
    ]
)]
class UserPublicationUploadImage
{
    public string $uri;
}

<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Publication\UserPublicationRemoveCoverProcessor;
use App\State\Processor\User\Publication\UserPublicationUploadCoverProcessor;
use App\State\Provider\User\Publication\UserPublicationEditProvider;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/user/publications/{id}/upload-cover',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            deserialize: false,
            name: 'api_user_publications_upload_cover',
            provider: UserPublicationEditProvider::class,
            processor: UserPublicationUploadCoverProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/publications/{id}/cover',
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_remove_cover',
            provider: UserPublicationEditProvider::class,
            processor: UserPublicationRemoveCoverProcessor::class,
        ),
    ]
)]
class UserPublicationUploadCover
{
    public string $uri;
}

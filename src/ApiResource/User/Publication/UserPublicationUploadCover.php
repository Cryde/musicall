<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\State\Processor\User\Publication\UserPublicationRemoveCoverProcessor;
use App\State\Processor\User\Publication\UserPublicationUploadCoverProcessor;
use App\State\Provider\User\Publication\UserPublicationEditProvider;
use App\State\Provider\User\Publication\UserPublicationUploadCoverProvider;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/user/publications/{id}/upload-cover',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['User Publications'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'imageFile' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            ),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            output: UserPublicationUploadCoverOutput::class,
            name: 'api_user_publications_upload_cover',
            provider: UserPublicationUploadCoverProvider::class,
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
    #[Vich\UploadableField(mapping: 'publication_image_cover', fileNameProperty: 'filePath')]
    #[Assert\NotNull]
    #[Assert\Image(maxSize: "4Mi", maxWidth: 4000, maxHeight: 4000)]
    public ?File $imageFile = null;
    public ?string $filePath = null;
}

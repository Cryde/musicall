<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\State\Processor\User\Publication\UserPublicationUploadImageProcessor;
use App\State\Provider\User\Publication\UserPublicationUploadImageProvider;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/user/publications/{id}/upload-image',
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
            output: UserPublicationUploadImageOutput::class,
            name: 'api_user_publications_upload_image',
            provider: UserPublicationUploadImageProvider::class,
            processor: UserPublicationUploadImageProcessor::class,
        ),
    ]
)]
class UserPublicationUploadImage
{
    #[Vich\UploadableField(mapping: 'publication_image', fileNameProperty: 'filePath')]
    #[Assert\NotNull]
    #[Assert\Image(maxSize: "4Mi", maxWidth: 4000, maxHeight: 4000)]
    public ?File $imageFile = null;
    public ?string $filePath = null;
}

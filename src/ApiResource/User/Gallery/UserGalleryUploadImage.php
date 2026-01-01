<?php declare(strict_types=1);

namespace App\ApiResource\User\Gallery;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\State\Processor\User\Gallery\UserGalleryUploadImageProcessor;
use App\State\Provider\User\Gallery\UserGalleryUploadImageProvider;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/user/galleries/{id}/upload-image',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['User Galleries'],
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
            output: UserGalleryImage::class,
            name: 'api_user_gallery_upload_image',
            provider: UserGalleryUploadImageProvider::class,
            processor: UserGalleryUploadImageProcessor::class,
        )
    ],
)]
class UserGalleryUploadImage
{
    #[Vich\UploadableField(mapping: 'gallery_image', fileNameProperty: 'filePath')]
    #[Assert\NotNull]
    #[Assert\Image(maxSize: "4Mi", maxWidth: 4000, maxHeight: 4000)]
    public ?File $imageFile = null;
    public ?string $filePath = null;
}

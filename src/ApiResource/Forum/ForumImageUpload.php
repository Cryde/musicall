<?php

declare(strict_types=1);

namespace App\ApiResource\Forum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\State\Processor\Forum\ForumImageUploadProcessor;
use App\Validator\ImageMimeTypes;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/forum/upload-image',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Forum'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'imageFile' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                            ],
                        ],
                    ])
                )
            ),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            output: ForumImageUploadOutput::class,
            name: 'api_forum_upload_image',
            processor: ForumImageUploadProcessor::class,
        ),
    ]
)]
class ForumImageUpload
{
    #[Vich\UploadableField(mapping: 'forum_image', fileNameProperty: 'filePath')]
    #[Assert\NotNull]
    #[Assert\Image(maxSize: '4Mi', maxWidth: 4000, maxHeight: 4000, mimeTypes: ImageMimeTypes::ALLOWED, mimeTypesMessage: ImageMimeTypes::INVALID_MESSAGE)]
    public ?File $imageFile = null;

    public ?string $filePath = null;
}

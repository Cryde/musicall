<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\State\Processor\User\Profile\UserProfileCoverPictureDeleteProcessor;
use App\State\Processor\User\Profile\UserProfileCoverPictureProcessor;
use App\State\Provider\User\Profile\UserProfileCoverPictureDeleteProvider;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/user/profile/cover-picture',
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Profile'],
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
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            name: 'api_user_profile_cover_picture_post',
            processor: UserProfileCoverPictureProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/profile/cover-picture',
            openapi: new Operation(tags: ['Profile']),
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            read: true,
            name: 'api_user_profile_cover_picture_delete',
            provider: UserProfileCoverPictureDeleteProvider::class,
            processor: UserProfileCoverPictureDeleteProcessor::class,
        ),
    ],
)]
class UserProfileCoverPictureUpload
{
    #[Vich\UploadableField(mapping: 'user_cover_picture', fileNameProperty: 'imageName', size: 'imageSize')]
    #[Assert\NotNull]
    #[Assert\Image(
        maxSize: '4Mi',
        minWidth: 800,
        maxWidth: 4000,
        maxHeight: 1000,
        minHeight: 200,
        maxRatio: 4.1,
        minRatio: 3.9,
        maxRatioMessage: 'L\'image doit avoir un ratio de 4:1 (largeur/hauteur)',
        minRatioMessage: 'L\'image doit avoir un ratio de 4:1 (largeur/hauteur)',
    )]
    public ?File $imageFile = null;

    public ?string $imageName = null;

    public ?int $imageSize = null;
}

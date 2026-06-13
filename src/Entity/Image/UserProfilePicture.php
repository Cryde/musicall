<?php

declare(strict_types=1);

namespace App\Entity\Image;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Comment\Comment;
use App\Entity\User;
use App\State\Processor\User\UserProfilePictureDeleteProcessor;
use App\State\Processor\User\UserProfilePictureProcessor;
use App\State\Provider\User\UserProfilePictureDeleteProvider;
use App\Validator\ImageMimeTypes;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new Post(
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Operation(
                tags: ['Users'],
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
            name: 'api_user_profile_picture_post',
            processor: UserProfilePictureProcessor::class
        ),
        new Delete(
            uriTemplate: '/user/profile-picture',
            openapi: new Operation(tags: ['Users']),
            security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
            read: true,
            name: 'api_user_profile_picture_delete',
            provider: UserProfilePictureDeleteProvider::class,
            processor: UserProfilePictureDeleteProcessor::class,
        ),
    ],
)]
class UserProfilePicture
{
    const POST = 'USER_PICTURE_POST';
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    // NOTE: This is not a mapped field of entity metadata, just a simple property.
    #[Assert\NotNull]
    #[Assert\Image(maxSize: "4Mi", minWidth: 450, maxWidth: 4000, maxHeight: 4000, minHeight: 450, allowLandscape: true, allowPortrait: true, mimeTypes: ImageMimeTypes::ALLOWED, mimeTypesMessage: ImageMimeTypes::INVALID_MESSAGE)]
    #[Vich\UploadableField(mapping: 'user_profile_picture', fileNameProperty: 'imageName', size: 'imageSize')]
    #[Groups([Comment::ITEM, Comment::LIST, MessageThreadMetaResource::LIST, User::ITEM])]
    public ?File $imageFile = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $imageName = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $imageSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updatedAt = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    public ?User $user = null;

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @return $this
     */
    public function setImageFile(?File $image = null): static
    {
        $this->imageFile = $image;
        if ($image instanceof \Symfony\Component\HttpFoundation\File\File) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }
}

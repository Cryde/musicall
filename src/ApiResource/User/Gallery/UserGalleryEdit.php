<?php declare(strict_types=1);

namespace App\ApiResource\User\Gallery;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Gallery\UserGalleryEditProcessor;
use App\State\Provider\User\Gallery\UserGalleryEditProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/user/galleries/{id}',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_get',
            provider: UserGalleryEditProvider::class,
        ),
        new Patch(
            uriTemplate: '/user/galleries/{id}',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_edit',
            provider: UserGalleryEditProvider::class,
            processor: UserGalleryEditProcessor::class,
        ),
    ]
)]
class UserGalleryEdit
{
    #[ApiProperty(identifier: true)]
    public int $id;

    #[Assert\NotBlank(message: 'Le titre est requis')]
    #[Assert\Length(min: 3, max: 200, minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres')]
    public string $title;

    public ?string $description = null;

    public int $status;

    public ?string $coverImageUrl = null;

    public ?int $coverImageId = null;
}

<?php declare(strict_types=1);

namespace App\ApiResource\User\Gallery;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Gallery\UserGalleryCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/user/galleries',
            openapi: new Operation(tags: ['User Galleries']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_gallery_add',
            processor: UserGalleryCreateProcessor::class,
        ),
    ]
)]
class UserGalleryCreate
{
    #[Assert\NotBlank(message: 'publication.gallery.title_required')]
    #[Assert\Length(min: 3, max: 200, minMessage: 'publication.gallery.title_too_short')]
    public string $title;
}

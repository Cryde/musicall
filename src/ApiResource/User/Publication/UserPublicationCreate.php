<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Publication\UserPublicationCreateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/user/publications',
    openapi: new Operation(tags: ['User Publications']),
    security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
    name: 'api_user_publications_create',
    processor: UserPublicationCreateProcessor::class,
)]
class UserPublicationCreate
{
    #[Assert\NotBlank(message: 'publication.title_required')]
    #[Assert\Length(min: 3, minMessage: 'publication.title_too_short')]
    public string $title;

    #[Assert\NotBlank(message: 'publication.category_required')]
    public int $categoryId;
}

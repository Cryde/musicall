<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Publication\UserPublicationEditProcessor;
use App\State\Processor\User\Publication\UserPublicationSubmitProcessor;
use App\State\Provider\User\Publication\UserPublicationEditProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/user/publications/{id}/edit',
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_get_edit',
            provider: UserPublicationEditProvider::class,
        ),
        new Patch(
            uriTemplate: '/user/publications/{id}',
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_patch',
            provider: UserPublicationEditProvider::class,
            processor: UserPublicationEditProcessor::class,
        ),
        new Post(
            uriTemplate: '/user/publications/{id}/submit',
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_submit',
            provider: UserPublicationEditProvider::class,
            processor: UserPublicationSubmitProcessor::class,
        ),
    ]
)]
class UserPublicationEdit
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public string $title;

    public string $slug;

    public ?string $shortDescription = null;

    public ?string $content = null;

    public int $statusId;

    public string $statusLabel;

    #[ApiProperty(genId: false)]
    public ?UserPublicationCategory $category = null;

    public ?int $categoryId = null;

    public ?string $coverUrl = null;

    /**
     * null = leave tags untouched. [] = clear all tags. Non-empty array = replace tag set.
     *
     * @var string[]|null
     */
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\NotBlank(message: 'Le tag ne peut pas être vide'),
        new Assert\Length(max: 100, maxMessage: 'Le tag est trop long (max {{ limit }} caractères)'),
    ])]
    #[Assert\Count(max: 20, maxMessage: 'Un maximum de {{ limit }} tags est autorisé')]
    public ?array $tags = null;
}

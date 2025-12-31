<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\User\Publication\UserPublicationPreviewProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/user/publications/{id}/preview',
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_preview',
            provider: UserPublicationPreviewProvider::class,
        ),
    ]
)]
class UserPublicationPreview
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

    #[ApiProperty(genId: false)]
    public ?UserPublicationPreviewAuthor $author = null;

    public ?string $coverUrl = null;
}

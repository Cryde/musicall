<?php declare(strict_types=1);

namespace App\ApiResource\User\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Publication;
use App\State\Processor\User\Publication\UserPublicationDeleteProcessor;
use App\State\Provider\User\Publication\UserPublicationCollectionProvider;
use App\State\Provider\User\Publication\UserPublicationDeleteProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/user/publications',
            openapi: new Operation(tags: ['User Publications']),
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            paginationClientItemsPerPage: true,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_get_collection',
            provider: UserPublicationCollectionProvider::class,
            parameters: [
                'status' => new QueryParameter(
                    key: 'status',
                    schema: ['type' => 'string', 'enum' => Publication::ALL_STATUS_STR],
                    description: 'Filter by status (0=draft, 1=online, 2=pending)',
                ),
                'category' => new QueryParameter(
                    key: 'category',
                    schema: ['type' => 'string', 'pattern' => '^\d+$'],
                    description: 'Filter by category ID',
                ),
                'sortBy' => new QueryParameter(
                    key: 'sortBy',
                    schema: ['type' => 'string', 'enum' => ['title', 'creation_datetime', 'edition_datetime']],
                    description: 'Sort field',
                ),
                'sortOrder' => new QueryParameter(
                    key: 'sortOrder',
                    schema: ['type' => 'string', 'enum' => ['asc', 'desc']],
                    description: 'Sort order',
                ),
            ],
        ),
        new Delete(
            uriTemplate: '/user/publications/{id}',
            openapi: new Operation(tags: ['User Publications']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_publications_delete',
            provider: UserPublicationDeleteProvider::class,
            processor: UserPublicationDeleteProcessor::class,
        ),
    ]
)]
class UserPublication
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public string $title;

    public string $slug;

    public \DateTimeInterface $creationDatetime;

    public ?\DateTimeInterface $editionDatetime = null;

    public int $statusId;

    public string $statusLabel;

    public int $typeId;

    public string $typeLabel;

    #[ApiProperty(genId: false)]
    public ?UserPublicationCategory $category = null;

    public ?string $coverUrl = null;
}

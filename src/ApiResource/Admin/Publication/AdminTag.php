<?php

declare(strict_types=1);

namespace App\ApiResource\Admin\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Admin\Publication\AdminTagCreateProcessor;
use App\State\Processor\Admin\Publication\AdminTagDeleteProcessor;
use App\State\Provider\Admin\Publication\AdminTagCollectionProvider;
use App\State\Provider\Admin\Publication\AdminTagItemProvider;
use App\State\Provider\Admin\Publication\AdminTagItemReadProvider;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AdminTag',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/tags',
            openapi: new Operation(tags: ['Admin Tags']),
            paginationEnabled: false,
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_tags_list',
            provider: AdminTagCollectionProvider::class,
        ),
        new Get(
            uriTemplate: '/admin/tags/{id}',
            openapi: new Operation(tags: ['Admin Tags']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_tags_get',
            provider: AdminTagItemReadProvider::class,
        ),
        new Post(
            uriTemplate: '/admin/tags',
            openapi: new Operation(tags: ['Admin Tags']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_tags_create',
            processor: AdminTagCreateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/admin/tags/{id}',
            status: Response::HTTP_NO_CONTENT,
            openapi: new Operation(tags: ['Admin Tags']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_tags_delete',
            provider: AdminTagItemProvider::class,
            processor: AdminTagDeleteProcessor::class,
        ),
    ]
)]
class AdminTag
{
    #[ApiProperty(identifier: true)]
    public int $id;

    #[Assert\NotBlank(message: 'Le label ne peut pas être vide')]
    #[Assert\Length(min: 1, max: 100, maxMessage: 'Le label est trop long (max {{ limit }} caractères)')]
    public string $label;

    public string $slug;
    public DateTimeInterface $creationDatetime;
    public int $publicationCount = 0;
}

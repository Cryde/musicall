<?php declare(strict_types=1);

namespace App\ApiResource\Comment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Comment\CommentCollectionProvider;
use App\State\Provider\Comment\CommentItemProvider;

#[ApiResource(
    shortName: 'Comment',
    operations: [
        new GetCollection(
            uriTemplate: '/comments',
            openapi: new Operation(tags: ['Comment']),
            paginationEnabled: false,
            name: 'api_comments_get_collection',
            provider: CommentCollectionProvider::class,
            parameters: [
                'thread' => new QueryParameter(key: 'thread'),
            ],
        ),
        new Get(
            uriTemplate: '/comments/{id}',
            openapi: new Operation(tags: ['Comment']),
            name: 'api_comments_get_item',
            provider: CommentItemProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class CommentResource
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public int $threadId;

    /** @var array{id: string, username: string, profile_picture_url: string|null, deletion_datetime: string|null} */
    public array $author;

    public string $content;

    public string $creationDatetime;

    public int $upvotes = 0;

    public int $downvotes = 0;

    public ?int $userVote = null;
}

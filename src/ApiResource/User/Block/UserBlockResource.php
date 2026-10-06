<?php declare(strict_types=1);

namespace App\ApiResource\User\Block;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Block\UserBlockCreateProcessor;
use App\State\Processor\User\Block\UserBlockDeleteProcessor;
use App\State\Provider\User\Block\UserBlockCollectionProvider;

/**
 * The users the caller has blocked (#1117). POST is idempotent and answers 201 every time, so the
 * clients never have to tell a first block from a repeat. The blocked user is never told.
 */
#[ApiResource(
    shortName: 'UserBlock',
    // A user without a picture still answers `profile_picture_url: null` rather than dropping the key.
    normalizationContext: ['skip_null_values' => false],
    operations: [
        new GetCollection(
            uriTemplate: '/user/blocks',
            openapi: new Operation(tags: ['User']),
            paginationEnabled: false,
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_blocks_get_collection',
            provider: UserBlockCollectionProvider::class,
        ),
        new Post(
            uriTemplate: '/user/blocks',
            openapi: new Operation(tags: ['User']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            input: UserBlockCreate::class,
            name: 'api_user_blocks_post',
            processor: UserBlockCreateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/blocks/{userId}',
            openapi: new Operation(tags: ['User']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            // The processor resolves the block against the caller, which no provider could do cheaper.
            read: false,
            name: 'api_user_blocks_delete',
            processor: UserBlockDeleteProcessor::class,
        ),
        // Not served, only the @id of a block, pinned to the same path as DELETE.
        new NotExposed(uriTemplate: '/user/blocks/{userId}'),
    ],
)]
class UserBlockResource
{
    #[ApiProperty(identifier: true)]
    public string $userId;

    public string $username;

    public string $displayName;

    public ?string $profilePictureUrl = null;

    public \DateTimeInterface $creationDatetime;
}

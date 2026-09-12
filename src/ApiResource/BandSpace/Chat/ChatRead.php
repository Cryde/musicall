<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatReadProcessor;

/**
 * « I have read the band's conversation », which is what clears the sidebar badge (#962).
 *
 * A command with nothing to say and nothing to return, so no input and a 204, like the restore
 * endpoint. The read position it moves is the member's own, not the space's content.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/read',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            status: 204,
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            input: false,
            output: false,
            name: 'api_band_space_chat_read',
            processor: ChatReadProcessor::class,
        ),
    ],
)]
class ChatRead
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;
}

<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatTypingProcessor;

/**
 * « I am writing » (#1040). The browser cannot publish to the hub, so it tells PHP, which does.
 * Nothing is stored: the signal is worth something for a few seconds and nothing after.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/typing',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            status: 204,
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            input: false,
            output: false,
            name: 'api_band_space_chat_typing',
            processor: ChatTypingProcessor::class,
        ),
    ],
)]
class ChatTyping
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;
}

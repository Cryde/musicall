<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatMessagePinProcessor;
use App\State\Processor\BandSpace\Chat\ChatMessageUnpinProcessor;

/**
 * « Keep this one at the top of the channel », and the reverse (#969).
 *
 * A command with nothing to send, like the restore endpoints, so no input. Both answer with the
 * message as it now stands rather than with a 204: pinning also flips the affordance on the message
 * in the list, and `loginUser()` aside, one round trip that returns the truth beats a client
 * guessing what the server just did.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages/{id}/pin',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: ChatMessageResource::class, identifiers: ['id']),
            ],
            status: 200,
            openapi: new Operation(tags: ['Band Space Chat']),
            normalizationContext: ['skip_null_values' => false],
            security: "is_granted('ROLE_USER')",
            input: false,
            output: ChatMessageResource::class,
            read: false,
            name: 'api_band_space_chat_messages_pin',
            processor: ChatMessagePinProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages/{id}/pin',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: ChatMessageResource::class, identifiers: ['id']),
            ],
            status: 200,
            openapi: new Operation(tags: ['Band Space Chat']),
            normalizationContext: ['skip_null_values' => false],
            security: "is_granted('ROLE_USER')",
            input: false,
            output: ChatMessageResource::class,
            read: false,
            name: 'api_band_space_chat_messages_unpin',
            processor: ChatMessageUnpinProcessor::class,
        ),
    ],
)]
class ChatMessagePin
{
}

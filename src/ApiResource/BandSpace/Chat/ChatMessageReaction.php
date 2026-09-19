<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Message\MessageReactionEmoji;
use App\State\Processor\BandSpace\Chat\ChatMessageReactionDeleteProcessor;
use App\State\Processor\BandSpace\Chat\ChatMessageReactionPostProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adding and removing one member's reaction to one chat message (#968).
 *
 * Two operations rather than a single toggle, deliberately: a double tap on a phone, or a retry after
 * a flaky connection, must not silently flip the state back to where it started. Adding a reaction
 * already held is therefore a no-op that answers with the message as it stands, not a 409.
 *
 * The emoji travels as its slug both ways, in the body on the way in and in the path on the way out,
 * so no multi-byte character ever has to survive a URL. See MessageReactionEmoji.
 */
#[ApiResource(
    shortName: 'ChatMessageReaction',
    operations: [
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages/{id}/reactions',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: ChatMessageResource::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['skip_null_values' => false],
            output: ChatMessageResource::class,
            name: 'api_band_space_chat_message_reactions_post',
            processor: ChatMessageReactionPostProcessor::class,
        ),
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages/{id}/reactions/{emoji}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: ChatMessageResource::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            // Nothing to read: the reaction to drop is named by the path, and the processor resolves
            // it against the viewer, which no provider could do more cheaply.
            read: false,
            name: 'api_band_space_chat_message_reactions_delete',
            processor: ChatMessageReactionDeleteProcessor::class,
        ),
    ],
)]
class ChatMessageReaction
{
    /**
     * A slug from the allow list, validated here rather than checked in the processor so an unknown
     * one is a 422 naming the field like every other bad input.
     *
     * One constraint rather than NotBlank as well: the default covers a body that omits the field
     * entirely, and Choice already refuses it, so adding NotBlank would only answer an empty string
     * with two violations saying the same thing.
     */
    #[Assert\Choice(callback: [MessageReactionEmoji::class, 'values'], message: 'Réaction inconnue')]
    public string $emoji = '';
}

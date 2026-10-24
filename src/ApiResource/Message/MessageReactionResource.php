<?php declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Enum\Message\MessageReactionEmoji;
use App\State\Processor\Message\MessageReactionDeleteProcessor;
use App\State\Processor\Message\MessageReactionPostProcessor;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adding and removing one reaction in a direct conversation (#1245). Two operations rather than a
 * toggle, and the emoji as its slug both ways, for the reasons ChatMessageReaction gives.
 */
#[ApiResource(
    shortName: 'MessageReaction',
    operations: [
        new Post(
            uriTemplate: '/message_threads/{threadId}/messages/{id}/reactions',
            uriVariables: [
                'threadId' => new Link(fromClass: MessageThreadResource::class, identifiers: ['id']),
                'id' => new Link(fromClass: MessageResource::class, identifiers: ['id']),
            ],
            requirements: ['threadId' => Requirement::UUID, 'id' => Requirement::UUID],
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => [MessageResource::LIST]],
            read: false,
            output: MessageResource::class,
            name: 'api_message_thread_message_reactions_post',
            processor: MessageReactionPostProcessor::class,
        ),
        new Delete(
            uriTemplate: '/message_threads/{threadId}/messages/{id}/reactions/{emoji}',
            uriVariables: [
                'threadId' => new Link(fromClass: MessageThreadResource::class, identifiers: ['id']),
                'id' => new Link(fromClass: MessageResource::class, identifiers: ['id']),
            ],
            requirements: ['threadId' => Requirement::UUID, 'id' => Requirement::UUID],
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            read: false,
            name: 'api_message_thread_message_reactions_delete',
            processor: MessageReactionDeleteProcessor::class,
        ),
    ],
)]
class MessageReactionResource
{
    /** One constraint, for the reason ChatMessageReaction::$emoji gives. */
    #[Assert\Choice(callback: [MessageReactionEmoji::class, 'values'], message: 'band_space.chat.reaction_unknown')]
    public string $emoji = '';
}

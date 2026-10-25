<?php declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Message\MessageDeleteProcessor;
use App\State\Processor\Message\MessageUpdateProcessor;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Editing and deleting your own message in a direct conversation (#1245), the band chat's
 * ChatMessageUpdate and delete for two people.
 *
 * Its own class rather than operations on MessageResource: that one is the read shape, and the only
 * writable thing here is the text. Nothing to read either: the processors resolve the message
 * themselves, so the participant and author rules apply before anything else happens.
 */
#[ApiResource(
    shortName: 'MessageUpdate',
    operations: [
        new Patch(
            uriTemplate: '/message_threads/{threadId}/messages/{id}',
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
            name: 'api_message_thread_messages_patch',
            processor: MessageUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/message_threads/{threadId}/messages/{id}',
            uriVariables: [
                'threadId' => new Link(fromClass: MessageThreadResource::class, identifiers: ['id']),
                'id' => new Link(fromClass: MessageResource::class, identifiers: ['id']),
            ],
            requirements: ['threadId' => Requirement::UUID, 'id' => Requirement::UUID],
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            read: false,
            name: 'api_message_thread_messages_delete',
            processor: MessageDeleteProcessor::class,
        ),
    ],
)]
class MessageUpdate
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    public string $content;
}

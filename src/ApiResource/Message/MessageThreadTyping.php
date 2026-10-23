<?php declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\Message\MessageThreadTypingProcessor;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * « X est en train d'écrire » in a direct conversation (#1242), the band chat's ChatTyping for two
 * people. Nothing is stored.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/message_threads/{threadId}/typing',
            uriVariables: [
                'threadId' => new Link(fromClass: self::class, identifiers: ['threadId']),
            ],
            requirements: ['threadId' => Requirement::UUID],
            status: 204,
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            input: false,
            output: false,
            name: 'api_message_thread_typing',
            processor: MessageThreadTypingProcessor::class,
        ),
    ],
)]
class MessageThreadTyping
{
    #[ApiProperty(identifier: true)]
    public string $threadId;
}

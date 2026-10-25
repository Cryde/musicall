<?php declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\ApiResource\BandSpace\Chat\ChatPresence;
use App\State\Processor\Message\MessageThreadPresenceLeaveProcessor;
use App\State\Processor\Message\MessageThreadPresenceProcessor;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * « En ligne » in a direct conversation (#1246), the band chat's ChatPresence for two people: the open
 * conversation beats every 30 seconds and the answer is whether the other person has it open too.
 */
#[ApiResource(
    operations: [
        // Not served: here so the answer's @id names the address it came from rather than an invented one.
        new NotExposed(
            uriTemplate: '/message_threads/{threadId}/presence',
            uriVariables: [
                'threadId' => new Link(fromClass: self::class, identifiers: ['threadId']),
            ],
            requirements: ['threadId' => Requirement::UUID],
        ),
        new Post(
            uriTemplate: '/message_threads/{threadId}/presence',
            uriVariables: [
                'threadId' => new Link(fromClass: self::class, identifiers: ['threadId']),
            ],
            requirements: ['threadId' => Requirement::UUID],
            status: 200,
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            input: false,
            read: false,
            name: 'api_message_thread_presence',
            parameters: [
                'tab' => new QueryParameter(key: 'tab', constraints: [
                    new Assert\Regex(pattern: ChatPresence::TAB_PATTERN, message: ChatPresence::TAB_MESSAGE),
                ]),
            ],
            processor: MessageThreadPresenceProcessor::class,
        ),
        // Sent with navigator.sendBeacon() as the page goes, which can only POST.
        new Post(
            uriTemplate: '/message_threads/{threadId}/presence/leave',
            uriVariables: [
                'threadId' => new Link(fromClass: self::class, identifiers: ['threadId']),
            ],
            requirements: ['threadId' => Requirement::UUID],
            status: 204,
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            input: false,
            output: false,
            read: false,
            name: 'api_message_thread_presence_leave',
            parameters: [
                'tab' => new QueryParameter(key: 'tab', constraints: [
                    new Assert\Regex(pattern: ChatPresence::TAB_PATTERN, message: ChatPresence::TAB_MESSAGE),
                ]),
            ],
            processor: MessageThreadPresenceLeaveProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class MessageThreadPresence
{
    #[ApiProperty(identifier: true)]
    public string $threadId;

    /**
     * The other participant when they have the conversation open and have not opted out. A list, as
     * in the band chat, so both clients read one shape.
     *
     * @var list<string>
     */
    public array $onlineUserIds = [];
}

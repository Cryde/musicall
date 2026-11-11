<?php declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Message\MessageWindowProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A stretch of a conversation anchored on one message (#1380), the band chat's ChatMessageWindow for
 * two people: what a click on a quote lands on when the answered message is above the loaded pages,
 * and how the pane reads on from there. Exactly one of `around`, `before` and `after`, see
 * MessageWindowReader.
 */
#[ApiResource(
    shortName: 'MessageWindow',
    operations: [
        new Get(
            uriTemplate: '/message_threads/{threadId}/message_window',
            uriVariables: [
                'threadId' => new Link(fromClass: self::class, identifiers: ['threadId']),
            ],
            requirements: ['threadId' => Requirement::UUID],
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => [MessageResource::LIST, self::READ]],
            name: 'api_message_thread_message_window_get',
            provider: MessageWindowProvider::class,
            parameters: [
                'around' => new QueryParameter(key: 'around', constraints: [new Assert\Uuid()]),
                'before' => new QueryParameter(key: 'before', constraints: [new Assert\Uuid()]),
                'after' => new QueryParameter(key: 'after', constraints: [new Assert\Uuid()]),
            ],
        ),
    ],
)]
class MessageWindow
{
    public const string READ = 'message_window:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::READ])]
    public string $threadId;

    /**
     * Oldest first, reading order, unlike the paginated list.
     *
     * @var MessageResource[]
     */
    #[ApiProperty(readableLink: true)]
    #[Groups([self::READ])]
    public array $messages = [];

    /** Whether the thread holds messages older than the oldest one here. */
    #[Groups([self::READ])]
    public bool $hasOlder = false;

    /** Whether it holds newer ones: false means this window reaches the live end of the conversation. */
    #[Groups([self::READ])]
    public bool $hasNewer = false;

    /** The thread's message count, what the paginated list reports, for the pane to fall back on. */
    #[Groups([self::READ])]
    public int $totalItems = 0;
}

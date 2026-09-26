<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Chat\ChatMessageWindowProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A stretch of the band's chat anchored on one message, wherever it sits in the history (#1039): what
 * a jump to a pinned message, a mention or a link lands on, and how the pane then reads on from there
 * in either direction.
 *
 * Its own operation beside the paginated list, not a mode of it: the list is offset paginated from
 * the newest message (#955), and an offset cannot say where a given message sits without counting
 * everything newer. This one is keyed on the anchor instead, `(creation_datetime, id)`.
 *
 * Exactly one of `around`, `before` and `after`:
 * - `around`: the anchor with up to 25 messages on each side, the landing window
 * - `before`: up to 50 messages older than the anchor
 * - `after`: up to 50 messages newer than the anchor
 */
#[ApiResource(
    shortName: 'ChatMessageWindow',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/message_window',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_chat_message_window_get',
            provider: ChatMessageWindowProvider::class,
            parameters: [
                'around' => new QueryParameter(key: 'around', constraints: [new Assert\Uuid()]),
                'before' => new QueryParameter(key: 'before', constraints: [new Assert\Uuid()]),
                'after' => new QueryParameter(key: 'after', constraints: [new Assert\Uuid()]),
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class ChatMessageWindow
{
    public const int AROUND_EACH_SIDE = 25;

    public const int PAGE_SIZE = 50;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    /**
     * Oldest first, reading order, unlike the paginated list: a window is read as it is, never
     * reversed page by page.
     *
     * @var ChatMessageResource[]
     */
    #[ApiProperty(readableLink: true)]
    public array $messages = [];

    /** Whether the thread holds messages older than the oldest one here. */
    public bool $hasOlder = false;

    /** Whether it holds newer ones: false means this window reaches the live end of the conversation. */
    public bool $hasNewer = false;

    /** The thread's message count, what the paginated list reports, for the pane to fall back on. */
    public int $totalItems = 0;
}

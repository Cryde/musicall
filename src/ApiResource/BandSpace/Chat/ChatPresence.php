<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Operation as MetadataOperation;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatPresenceLeaveProcessor;
use App\State\Processor\BandSpace\Chat\ChatPresenceProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * « En ligne » in the band's chat (#1040). The open chat beats every 30 seconds and the answer is who
 * else is there, so one request both announces and reads.
 */
#[ApiResource(
    operations: [
        // Not served: here so the answer's @id names the address it came from rather than an invented one.
        new NotExposed(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/presence',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
        ),
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/presence',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            status: 200,
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            input: false,
            read: false,
            name: 'api_band_space_chat_presence',
            parameters: [
                'tab' => new QueryParameter(key: 'tab', constraints: [
                    new Assert\Regex(pattern: self::TAB_PATTERN, message: self::TAB_MESSAGE),
                ]),
            ],
            processor: ChatPresenceProcessor::class,
        ),
        // Sent with navigator.sendBeacon() as the page goes, which can only POST.
        new Post(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/presence/leave',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            status: 204,
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            input: false,
            output: false,
            read: false,
            name: 'api_band_space_chat_presence_leave',
            parameters: [
                'tab' => new QueryParameter(key: 'tab', constraints: [
                    new Assert\Regex(pattern: self::TAB_PATTERN, message: self::TAB_MESSAGE),
                ]),
            ],
            processor: ChatPresenceLeaveProcessor::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class ChatPresence
{
    /** A tab's id, minted by the page it identifies; one member can have the chat open in several. */
    public const string TAB_PATTERN = '/^[A-Za-z0-9-]{8,64}\z/';
    public const string TAB_MESSAGE = 'Identifiant d\'onglet invalide';

    /** The already validated `tab`; a client that sends none counts as a single tab. */
    public static function tabOf(MetadataOperation $operation): string
    {
        $tab = $operation->getParameters()?->get('tab')?->getValue();

        return is_string($tab) && $tab !== '' ? $tab : 'default';
    }

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    /**
     * The other members with the chat open who have not opted out.
     *
     * @var list<string>
     */
    public array $onlineUserIds = [];
}

<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatMessageDeleteProcessor;
use App\State\Provider\BandSpace\Chat\ChatMessageCollectionProvider;
use App\State\Provider\BandSpace\Chat\ChatPinnedMessageCollectionProvider;
use DateTimeInterface;

/**
 * The band's chat, read.
 *
 * Offset paginated rather than cursor paginated, which is what #960 originally called for: #955
 * settled that question the other way for the same table, because `Message.id` is uuid4 and carries
 * no order, so a correct cursor would have to be a `(creation_datetime, id)` composite that API
 * Platform's paginationViaCursor cannot express. The client merges pages by `@id` instead, and
 * assets/js/utils/messagePagination.js already does it for the direct message thread.
 */
#[ApiResource(
    shortName: 'ChatMessage',
    operations: [
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 200,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_chat_messages_get_collection',
            provider: ChatMessageCollectionProvider::class,
        ),
        // read: false because there is no item provider to read with, and writing one would repeat the
        // membership check and the lookup the processor has to do anyway.
        new Delete(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            read: false,
            name: 'api_band_space_chat_messages_delete',
            processor: ChatMessageDeleteProcessor::class,
        ),
        // Its own collection rather than a filter on the list above: a pinned message is nearly
        // always far up the history, so the pane has not loaded it (#969). Capped at ten by
        // ChatMessagePinProcessor, so there is nothing to paginate.
        new GetCollection(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/pinned_messages',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            paginationEnabled: false,
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_chat_pinned_messages_get_collection',
            provider: ChatPinnedMessageCollectionProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class ChatMessageResource
{
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    public string $authorId;

    /** `Utilisateur supprimé` once the account is gone, so the renderer never prints `deleted_<uuid>`. */
    public string $authorUsername;

    public ?string $authorProfilePictureUrl = null;

    /** Sanitized to text plus `<br>`, like the direct message thread. Rendered with v-html. */
    public string $content;

    public DateTimeInterface $creationDatetime;

    /**
     * One entry per emoji somebody used, in MessageReactionEmoji declaration order, an emoji nobody
     * used being absent rather than present with a zero (#968).
     *
     * Snake_case inside the array on purpose: the name converter renames properties, not the keys of
     * an array a property holds, so these are written the way they go out.
     *
     * @var list<array{key: string, emoji: string, count: int, has_reacted: bool}>
     */
    public array $reactions = [];

    /**
     * The Band Space objects this message points at, resolved into cards (#970), one entry per
     * attachment row, ordered by kind then target id:
     * `{"type": "task", "target_id": "<uuid>", "label": "Réparer l'ampli", "is_available": true}`.
     *
     * `label` is always the snapshot taken when the target was attached, never its title read back
     * now, so every reader of the channel sees the same card. `is_available` false means the target
     * has been deleted, and the client then renders the label with a « (supprimé) » suffix and no
     * link. The deep link is built client side from `type` and `target_id`, by the same mapping the
     * command palette uses, because those paths belong to the Vue router.
     *
     * @var list<array{type: string, target_id: string, label: string, is_available: bool}>
     */
    public array $attachments = [];

    /** Null until the author edits it, which is what the « modifié » marker reads (#966). */
    public ?DateTimeInterface $updateDatetime = null;

    /**
     * The message as it is **stored**, with its `@[uuid]` tokens intact, and only for the member who
     * wrote it (#966).
     *
     * `content` above is rendered: sanitized, and with its mentions already turned into spans by
     * ChatMentionRenderer. MentionEditor round trips the stored format, so an edit box seeded from
     * `content` would offer somebody their own message with the markup in it. Nothing is leaked by
     * shipping it, since it is the viewer's own text, and gating it on authorship keeps the payload
     * honest about who the PATCH will accept.
     */
    public ?string $editableContent = null;

    /**
     * Deleted, which the client paints as « Message supprimé » (#967). The author, the avatar and the
     * time stay, because that is what keeps the thread readable; `content` comes back empty.
     */
    public bool $isDeleted = false;

    public bool $isPinned = false;

    public ?DateTimeInterface $pinnedDatetime = null;

    /** `Utilisateur supprimé` once the account is gone, exactly like the author field above. */
    public ?string $pinnedByUsername = null;

    /**
     * Who has read this message: every active member whose read position has reached it, by username
     * and never the author, since writing something is not reading it (#977).
     *
     * A member who has never opened the channel has no read-state row at all, membership being
     * derived from the band space, so they are simply absent, which is unread and not missing. A
     * former member is absent too, although their row survives. A tombstone reports nobody.
     *
     * @var list<string>
     */
    public array $readByUsernames = [];

    /** How many the list above holds, so the client can say « et 3 autres » without counting. */
    public int $readCount = 0;
}

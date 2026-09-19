<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Chat\ChatMessageCollectionProvider;
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
}

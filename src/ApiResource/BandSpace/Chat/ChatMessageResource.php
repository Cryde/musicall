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
}

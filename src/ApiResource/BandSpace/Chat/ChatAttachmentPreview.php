<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Chat\ChatAttachmentPreviewProvider;

/**
 * One Band Space object named by a pasted URL, resolved for the sender (#972): what the composer's
 * chip reads, and the server's word on whether this member may point at it at all.
 *
 * The same fields as a BandSpaceSearchResult, so the composer adds it to its draft exactly as it adds
 * a pick from the attachment picker.
 */
#[ApiResource(
    shortName: 'ChatAttachmentPreview',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/attachment_previews/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            name: 'api_band_space_chat_attachment_previews_get',
            provider: ChatAttachmentPreviewProvider::class,
        ),
    ],
)]
class ChatAttachmentPreview
{
    /** The synthetic `<type>-<uuid>` identifier a chat message's `attachments` takes. */
    #[ApiProperty(identifier: true)]
    public string $id;

    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    /** A BandSpaceSearchResultType value. */
    public string $type;

    public string $resourceId;

    public string $title;
}

<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\BandSpace\Chat\ChatImageProvider;

/**
 * An image posted in the chat, streamed inline so the bubble can show it (#973). Every other band
 * space download is an attachment; this one is limited to files the chat itself stored.
 */
#[ApiResource(
    shortName: 'ChatImage',
    operations: [
        new Get(
            uriTemplate: '/band_spaces/{bandSpaceId}/chat/images/{id}',
            uriVariables: [
                'bandSpaceId' => new Link(fromClass: self::class, identifiers: ['bandSpaceId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            openapi: new Operation(tags: ['Band Space Chat']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: 'api_band_space_chat_images_get',
            provider: ChatImageProvider::class,
        ),
    ],
)]
class ChatImage
{
    #[ApiProperty(identifier: true)]
    public string $bandSpaceId;

    /** The band space file holding the image. */
    #[ApiProperty(identifier: true)]
    public string $id;
}

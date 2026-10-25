<?php declare(strict_types=1);

namespace App\ApiResource\Message;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Message\MessageThreadMediaProvider;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * An image or a voice note of a direct conversation (#1247), streamed inline to its two participants,
 * as the band chat streams its own (#973, #974).
 */
#[ApiResource(
    shortName: 'MessageThreadMedia',
    operations: [
        new Get(
            uriTemplate: '/message_threads/{threadId}/images/{id}',
            uriVariables: [
                'threadId' => new Link(fromClass: self::class, identifiers: ['threadId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['threadId' => Requirement::UUID, 'id' => Requirement::UUID],
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: self::IMAGE_OPERATION,
            provider: MessageThreadMediaProvider::class,
        ),
        new Get(
            uriTemplate: '/message_threads/{threadId}/voice_notes/{id}',
            uriVariables: [
                'threadId' => new Link(fromClass: self::class, identifiers: ['threadId']),
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            requirements: ['threadId' => Requirement::UUID, 'id' => Requirement::UUID],
            openapi: new Operation(tags: ['Message']),
            security: "is_granted('ROLE_USER')",
            output: false,
            name: self::VOICE_NOTE_OPERATION,
            provider: MessageThreadMediaProvider::class,
        ),
    ],
)]
class MessageThreadMedia
{
    public const string IMAGE_OPERATION = 'api_message_thread_images_get';
    public const string VOICE_NOTE_OPERATION = 'api_message_thread_voice_notes_get';

    #[ApiProperty(identifier: true)]
    public string $threadId;

    /** The message file holding the image or the voice note. */
    #[ApiProperty(identifier: true)]
    public string $id;
}

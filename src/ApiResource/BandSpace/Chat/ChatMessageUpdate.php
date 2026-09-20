<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatMessageUpdateProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Editing your own message (#966).
 *
 * Its own input class rather than a Patch on ChatMessageResource, for the reason the Post already
 * has one: the read resource carries the rendered content and the author, none of which a client
 * may send, and the only writable thing here is the text.
 */
#[Patch(
    uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages/{id}',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
        'id' => new Link(fromClass: ChatMessageResource::class, identifiers: ['id']),
    ],
    openapi: new Operation(tags: ['Band Space Chat']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    // Nothing to read: the processor resolves the message itself so that it can apply the author
    // rule before anything else happens, exactly as BandSpaceMemberProfile does.
    read: false,
    output: ChatMessageResource::class,
    name: 'api_band_space_chat_messages_patch',
    processor: ChatMessageUpdateProcessor::class,
)]
class ChatMessageUpdate
{
    #[Assert\NotBlank(message: 'Veuillez saisir un message')]
    #[Assert\Length(max: 5000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères')]
    public string $content;
}

<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatMessagePostProcessor;
use App\Validator\Message\ValidChatAttachments;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
    ],
    openapi: new Operation(tags: ['Band Space Chat']),
    security: "is_granted('ROLE_USER')",
    normalizationContext: ['skip_null_values' => false],
    output: ChatMessageResource::class,
    name: 'api_band_space_chat_messages_post',
    processor: ChatMessagePostProcessor::class,
)]
class ChatMessageCreate
{
    /**
     * A chat reference is a pointer, not a bundle: past a handful the message stops being a sentence
     * and becomes a list, which the band space's own modules already do better.
     */
    private const int MAX_ATTACHMENTS = 5;

    #[Assert\NotBlank(message: 'Veuillez saisir un message')]
    #[Assert\Length(max: 5000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères')]
    public string $content;

    /**
     * The Band Space objects this message points at, as the synthetic `<type>-<uuid>` identifiers
     * BandSpaceSearchResult already uses, which is exactly what the search endpoint hands the client.
     *
     * Sequentially, so a message over the cap is answered with the cap alone rather than with a
     * violation per entry on top of it.
     *
     * @var mixed[]
     */
    #[Assert\Sequentially([
        new Assert\Count(max: self::MAX_ATTACHMENTS, maxMessage: 'Un message ne peut pas référencer plus de {{ limit }} éléments'),
        new ValidChatAttachments(),
    ])]
    public array $attachments = [];
}

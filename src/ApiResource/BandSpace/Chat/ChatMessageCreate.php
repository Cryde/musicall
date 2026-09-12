<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\BandSpace\Chat\ChatMessagePostProcessor;
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
    #[Assert\NotBlank(message: 'Veuillez saisir un message')]
    #[Assert\Length(max: 5000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères')]
    public string $content;
}

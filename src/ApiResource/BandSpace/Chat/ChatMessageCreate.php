<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\Chat;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Serializer\Encoder\MultipartDecoder;
use App\Service\BandSpace\Chat\ChatImageConverter;
use App\Service\BandSpace\Chat\ChatVoiceNoteConverter;
use App\State\Processor\BandSpace\Chat\ChatMessagePostProcessor;
use App\Validator\Message\ValidChatAttachments;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/band_spaces/{bandSpaceId}/chat/messages',
    uriVariables: [
        'bandSpaceId' => new Link(fromClass: ChatMessageResource::class, identifiers: ['bandSpaceId']),
    ],
    // Multipart for a message carrying an image (#973), JSON otherwise. One operation rather than a
    // second endpoint, so mentions, attachments, the rate limit and the mention event stay on one path.
    inputFormats: [
        'jsonld' => ['application/ld+json'],
        'json' => ['application/json'],
        'multipart' => ['multipart/form-data'],
    ],
    openapi: new Operation(tags: ['Band Space Chat']),
    security: "is_granted('ROLE_USER')",
    denormalizationContext: [MultipartDecoder::RAW_FIELDS => ['content']],
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
     *
     * Public since #979, which adds a card to a message that already exists and has to hold the same
     * ceiling: one number, or the two doors disagree about how long a bubble may get.
     */
    public const int MAX_ATTACHMENTS = 5;

    /**
     * Optional once the message names an attachment or carries an image or a voice note: each is a
     * message on its own. Wrapped rather than replaced, so an empty attachment list is refused with exactly the
     * violation it always was. Defaults to empty so a body carrying only attachments reaches the
     * processor.
     */
    #[Assert\When(
        expression: 'this.attachments == [] and this.image === null and this.voiceNote === null',
        constraints: [new Assert\NotBlank(message: 'Veuillez saisir un message')],
    )]
    #[Assert\Length(max: 5000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères')]
    public string $content = '';

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

    /**
     * A pasted or dropped image (#973), stored as a band space file and converted to WebP by
     * ChatImageConverter. Only on a multipart body.
     */
    #[Assert\Image(
        maxSize: ChatImageConverter::MAX_UPLOAD_SIZE,
        mimeTypes: ChatImageConverter::ACCEPTED_MIME_TYPES,
        maxPixels: ChatImageConverter::MAX_PIXELS,
        maxSizeMessage: 'L\'image est trop volumineuse ({{ size }} {{ suffix }}), la limite est de {{ limit }} {{ suffix }}',
        mimeTypesMessage: 'Format d\'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)',
        maxPixelsMessage: 'L\'image est trop grande ({{ pixels }} pixels), la limite est de {{ max_pixels }} pixels',
    )]
    public ?File $image = null;

    /**
     * A recorded voice note (#974), converted to AAC by ChatVoiceNoteConverter. Sent alone, like on
     * every messaging app: no text, no image, no attachment beside it.
     */
    #[Assert\File(
        maxSize: ChatVoiceNoteConverter::MAX_UPLOAD_SIZE,
        mimeTypes: ChatVoiceNoteConverter::ACCEPTED_MIME_TYPES,
        maxSizeMessage: 'La note vocale est trop volumineuse ({{ size }} {{ suffix }}), la limite est de {{ limit }} {{ suffix }}',
        mimeTypesMessage: 'Format de note vocale non pris en charge',
    )]
    #[Assert\When(
        expression: 'value !== null',
        constraints: [new Assert\Expression(
            expression: 'this.content == "" and this.image === null and this.attachments == []',
            message: 'Une note vocale s\'envoie seule, sans texte, image ni pièce jointe',
        )],
    )]
    public ?File $voiceNote = null;
}

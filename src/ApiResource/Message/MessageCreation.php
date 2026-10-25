<?php

declare(strict_types=1);

namespace App\ApiResource\Message;

use App\Service\BandSpace\Chat\ChatImageConverter;
use App\Service\BandSpace\Chat\ChatVoiceNoteConverter;
use App\Validator\Message\NotDeletedThreadRecipient;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input DTO for `POST /api/messages`. Splits the input contract from the
 * response so the IRI denormalization of `thread` resolves through
 * `MessageThreadResource` (DTO) instead of forcing the entity into
 * `MessageResource->thread`.
 */
#[NotDeletedThreadRecipient]
class MessageCreation
{
    #[Assert\NotNull]
    public MessageThreadResource $thread;

    /** Optional once the message carries an image or a voice note (#1247), each a message on its own. */
    #[Assert\When(
        expression: 'this.image === null and this.voiceNote === null',
        constraints: [new Assert\NotBlank()],
    )]
    #[Assert\Length(max: 5000)]
    public string $content = '';

    /** An image, with the band chat's limits (#973): converted to WebP by ChatImageConverter. */
    #[Assert\Image(
        maxSize: ChatImageConverter::MAX_UPLOAD_SIZE,
        mimeTypes: ChatImageConverter::ACCEPTED_MIME_TYPES,
        maxPixels: ChatImageConverter::MAX_PIXELS,
        maxSizeMessage: 'band_space.chat.image_too_large',
        mimeTypesMessage: 'band_space.chat.image_type_invalid',
        maxPixelsMessage: 'band_space.chat.image_too_many_pixels',
    )]
    public ?File $image = null;

    /** A recorded voice note (#974), sent alone as in the band chat. */
    #[Assert\File(
        maxSize: ChatVoiceNoteConverter::MAX_UPLOAD_SIZE,
        mimeTypes: ChatVoiceNoteConverter::ACCEPTED_MIME_TYPES,
        maxSizeMessage: 'band_space.chat.voice_note_too_large',
        mimeTypesMessage: 'band_space.chat.voice_note_type_invalid',
    )]
    #[Assert\When(
        expression: 'value !== null',
        constraints: [new Assert\Expression(
            expression: 'this.content == "" and this.image === null',
            message: 'band_space.chat.voice_note_must_be_alone',
        )],
    )]
    public ?File $voiceNote = null;

    public function hasMedia(): bool
    {
        return $this->image instanceof File || $this->voiceNote instanceof File;
    }
}

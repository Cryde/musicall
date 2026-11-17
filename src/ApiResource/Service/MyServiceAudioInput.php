<?php

declare(strict_types=1);

namespace App\ApiResource\Service;

use App\Service\Service\ServiceAudioConverter;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/** One half of « Avant, après » (#1410), sent as multipart. */
class MyServiceAudioInput
{
    #[Assert\NotNull(message: 'service.audio.file_required')]
    #[Assert\File(
        maxSize: ServiceAudioConverter::MAX_UPLOAD_SIZE,
        mimeTypes: ServiceAudioConverter::ACCEPTED_MIME_TYPES,
        maxSizeMessage: 'service.audio.too_large',
        mimeTypesMessage: 'service.audio.type_invalid',
    )]
    public ?File $file = null;

    #[Assert\Length(max: 100)]
    public ?string $title = null;

    #[Assert\Length(max: 150)]
    public ?string $credit = null;
}

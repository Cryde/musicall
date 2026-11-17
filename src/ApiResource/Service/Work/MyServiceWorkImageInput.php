<?php

declare(strict_types=1);

namespace App\ApiResource\Service\Work;

use App\Entity\Service\ServiceWorkImage;
use App\Service\BandSpace\Chat\ChatImageConverter;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/** One image of a work (#1407), converted like a wall photo once accepted. */
class MyServiceWorkImageInput
{
    #[Assert\NotNull(message: 'service.work.image_required')]
    #[Assert\Image(
        maxSize: ServiceWorkImage::MAX_UPLOAD_SIZE,
        mimeTypes: ServiceWorkImage::ACCEPTED_MIME_TYPES,
        maxPixels: ChatImageConverter::MAX_PIXELS,
        maxSizeMessage: 'service.work.image_too_large',
        mimeTypesMessage: 'service.work.image_type_invalid',
        maxPixelsMessage: 'band_space.chat.image_too_many_pixels',
    )]
    public ?File $file = null;
}

<?php declare(strict_types=1);

namespace App\ApiResource\Publication;

class GalleryImage
{
    /** @var array{small?: string, medium?: string, full?: string} */
    public array $sizes = [];
}

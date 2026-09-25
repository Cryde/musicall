<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use Symfony\Component\HttpFoundation\File\File;

final readonly class ConvertedChatImage
{
    public function __construct(
        public File $file,
        public string $mimeType,
        // A converted image lives in a temporary file the caller has to remove once it is stored.
        public bool $isTemporary,
    ) {
    }
}

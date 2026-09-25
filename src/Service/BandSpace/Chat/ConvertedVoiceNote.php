<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use Symfony\Component\HttpFoundation\File\File;

final readonly class ConvertedVoiceNote
{
    public function __construct(
        // A temporary file, which the caller removes once it is stored.
        public File $file,
        public int $durationSeconds,
        /** @var list<int> */
        public array $peaks,
    ) {
    }
}

<?php declare(strict_types=1);

namespace App\Service\BandSpace\Chat;

use App\Entity\BandSpace\BandSpaceFile;

final readonly class StoredVoiceNote
{
    public function __construct(
        public BandSpaceFile $file,
        public int $durationSeconds,
        /** @var list<int> */
        public array $peaks,
    ) {
    }
}

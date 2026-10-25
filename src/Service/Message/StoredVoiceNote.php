<?php declare(strict_types=1);

namespace App\Service\Message;

/**
 * A stored voice note and what was measured on it: a band space file in the band chat, a message
 * file in a direct conversation.
 *
 * @template TFile of object
 */
final readonly class StoredVoiceNote
{
    /**
     * @param TFile     $file
     * @param list<int> $peaks
     */
    public function __construct(
        public object $file,
        public int $durationSeconds,
        public array $peaks,
    ) {
    }
}

<?php declare(strict_types=1);

namespace App\Service\BandSpace\RichText;

/**
 * Why a document is out of the format: a `validators` key, and the parameters its message needs.
 */
final readonly class TipTapDocumentProblem
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        public string $messageKey,
        public array $parameters = [],
    ) {
    }
}

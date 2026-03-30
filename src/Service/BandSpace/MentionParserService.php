<?php declare(strict_types=1);

namespace App\Service\BandSpace;

readonly class MentionParserService
{
    /**
     * Extracts unique user UUIDs from @[uuid] patterns in text.
     *
     * @return string[]
     */
    public function extractMentions(string $text): array
    {
        preg_match_all(
            '/@\[([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\]/i',
            $text,
            $matches
        );

        return array_values(array_unique($matches[1]));
    }
}

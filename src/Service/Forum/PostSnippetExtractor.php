<?php declare(strict_types=1);

namespace App\Service\Forum;

/**
 * Extracts a ~150-char window of plain text centred on the first case-insensitive
 * match of any term. Forum post content is stored as plain text (raw newlines)
 * but tags are stripped defensively in case future content carries them.
 *
 * Returns plain text - the frontend wraps matches in <mark>. That keeps the API
 * payload HTML-free and avoids cross-context escaping bugs.
 */
final readonly class PostSnippetExtractor
{
    private const int WINDOW_LENGTH = 150;
    private const int CONTEXT_BEFORE = 40;

    public function extract(string $content, string $term): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        if ($plain === '') {
            return '';
        }

        $matchOffset = $this->firstMatchOffset($plain, $term);
        if ($matchOffset === null) {
            return $this->ellipsised($plain, 0);
        }

        $start = max(0, $matchOffset - self::CONTEXT_BEFORE);

        return $this->ellipsised($plain, $start);
    }

    /**
     * Returns the byte offset of the first occurrence of any whitespace-separated
     * token from $term in $content, case-insensitive, or null when none match.
     */
    private function firstMatchOffset(string $content, string $term): ?int
    {
        $tokens = array_filter(
            preg_split('/\s+/u', trim($term)) ?: [],
            static fn(string $token): bool => $token !== '',
        );

        $earliest = null;
        $haystack = mb_strtolower($content, 'UTF-8');
        foreach ($tokens as $token) {
            $needle = mb_strtolower($token, 'UTF-8');
            $position = mb_strpos($haystack, $needle, 0, 'UTF-8');
            if ($position === false) {
                continue;
            }
            // Convert mb position to byte offset for substr-based windowing.
            $byteOffset = strlen(mb_substr($content, 0, $position, 'UTF-8'));
            if ($earliest === null || $byteOffset < $earliest) {
                $earliest = $byteOffset;
            }
        }

        return $earliest;
    }

    private function ellipsised(string $content, int $start): string
    {
        $length = strlen($content);
        $end = min($length, $start + self::WINDOW_LENGTH);
        $snippet = substr($content, $start, $end - $start);

        if ($start > 0) {
            $snippet = '…' . $snippet;
        }
        if ($end < $length) {
            $snippet .= '…';
        }

        return $snippet;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Message;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * A stored message as plain text: no markup left, and nothing a browser would still have to decode.
 *
 * Shared by the inbox preview and by the task a message can become (#979). The decode is the half
 * that is easy to forget: the sanitizer escapes `@`, `"` and `=`, so its output is HTML, and a task
 * title read straight out of it would carry `&#64;` where the member wrote an `@`.
 */
readonly class MessagePlainTextExtractor
{
    public function __construct(
        #[Target('app.plain_text_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    /**
     * Line breaks kept, because a message written as a list is still a list once it is a task
     * description.
     */
    public function extract(string $content): string
    {
        return trim(html_entity_decode(
            $this->sanitizer->sanitize($content),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        ));
    }

    /** Collapsed onto one line, for the places where only one line fits. */
    public function extractOneLine(string $content): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->extract($content)));
    }
}

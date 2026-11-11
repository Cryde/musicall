<?php

declare(strict_types=1);

namespace App\Service\Message;

use App\Service\BandSpace\ChatMentionRenderer;

/**
 * A stored message on one plain line, mentions as names: the push body, the inbox preview and the
 * quote a reply shows (#1377) all read a message this way.
 */
readonly class MessagePreviewText
{
    public function __construct(
        private MessagePlainTextExtractor $plainTextExtractor,
        private ChatMentionRenderer $chatMentionRenderer,
    ) {
    }

    /**
     * Mentions only in a channel: a member typing a literal `@[...]` into a direct message means those characters.
     *
     * @param array<string, string> $mentionUsernamesById
     * @param string|null           $locale               the reader's when it is not the request's (a push recipient)
     */
    public function oneLine(string $content, array $mentionUsernamesById, bool $isChannel, ?string $locale = null): string
    {
        $text = $isChannel
            ? $this->chatMentionRenderer->renderPlain($content, $mentionUsernamesById, $locale)
            : $content;

        return $this->plainTextExtractor->extractOneLine($text);
    }

    public static function shorten(string $text, int $maxLength): string
    {
        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength - 1) . '…' : $text;
    }
}

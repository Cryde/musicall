<?php

declare(strict_types=1);

namespace App\Service\BandSpace;

/**
 * Turns the stored `@[uuid]` tokens of a chat message into something a browser can show (#964).
 *
 * **This runs on already-sanitized content, and that order is the whole of its safety.** Everything
 * the sender typed has been escaped by then, so the only markup in the result is the span below, and
 * the only variable inside it is a username, which is escaped here. Doing it the other way round, or
 * letting the client build this string, would mean injecting a username that has never been through
 * any sanitizer into an `innerHTML`: `assets/js/utils/autoLink.js` is explicit that it does no
 * escaping of its own and relies entirely on the server having already done it.
 *
 * The `@` is matched in both forms on purpose. `app.onlybr_sanitizer` turns it into `&#64;`, measured,
 * so the escaped form is the one that actually occurs today; accepting the bare one as well means a
 * change in how the sanitizer escapes cannot silently stop mentions rendering. ChatMessageBuilderTest
 * pins the real pipeline so such a change fails loudly rather than quietly.
 */
readonly class ChatMentionRenderer
{
    /** What `@[tous]` prints. The token is defined once, in ChatMentionResolver. */
    private const string EVERYONE_LABEL = 'tous';

    /** Somebody the message named who has no mention row: only legacy messages and corrupt data. */
    private const string UNKNOWN_LABEL = 'inconnu';

    private const string TOKEN_PATTERN = '/(?:@|&#64;)\[([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|tous)\]/i';

    /**
     * @param array<string, string> $usernamesById the names this message's mention rows resolve to,
     *                                             lower-cased ids, from MessageMentionRepository
     */
    public function render(string $sanitizedContent, array $usernamesById): string
    {
        return $this->replaceTokens(
            $sanitizedContent,
            fn (string $label): string => $this->span($label),
            $usernamesById,
        );
    }

    /**
     * The same tokens as plain text, for a preview that is never rendered as HTML (#994).
     *
     * The inbox lists a channel beside direct messages and builds its one-line preview from the
     * stored content, so without this a message naming somebody reads there as `@[3f2a...]`. Sharing
     * the pattern with render() is the whole point: a second copy of it would drift.
     *
     * @param array<string, string> $usernamesById
     */
    public function renderPlain(string $content, array $usernamesById): string
    {
        return $this->replaceTokens(
            $content,
            static fn (string $label): string => '@' . $label,
            $usernamesById,
        );
    }

    /**
     * @param callable(string): string $format  what one resolved label prints as
     * @param array<string, string>    $usernamesById
     */
    private function replaceTokens(string $content, callable $format, array $usernamesById): string
    {
        $rendered = preg_replace_callback(
            self::TOKEN_PATTERN,
            fn (array $matches): string => $format($this->labelFor($matches[1], $usernamesById)),
            $content,
        );

        // preg_replace_callback returns null only on a backtrack limit or bad UTF-8. Showing the
        // message with its raw tokens beats showing nothing at all.
        return $rendered ?? $content;
    }

    /**
     * @param array<string, string> $usernamesById
     */
    private function labelFor(string $token, array $usernamesById): string
    {
        if (mb_strtolower($token) === self::EVERYONE_LABEL) {
            return self::EVERYONE_LABEL;
        }

        return $usernamesById[mb_strtolower($token)] ?? self::UNKNOWN_LABEL;
    }

    private function span(string $label): string
    {
        return '<span class="chat-mention">@'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</span>';
    }
}

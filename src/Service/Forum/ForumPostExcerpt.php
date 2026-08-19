<?php

declare(strict_types=1);

namespace App\Service\Forum;

/**
 * Plain-text preview of a forum post, for the admin screens that list posts without rendering their
 * HTML (the moderation queue and the recent-activity panel).
 */
readonly class ForumPostExcerpt
{
    private const int MAX_LENGTH = 120;

    public function create(string $content): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
        if (mb_strlen($plain) <= self::MAX_LENGTH) {
            return $plain;
        }

        return mb_substr($plain, 0, self::MAX_LENGTH - 1) . '…';
    }
}

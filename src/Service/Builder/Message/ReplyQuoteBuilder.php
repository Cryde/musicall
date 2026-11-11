<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\Entity\Message\Message;
use App\Service\Message\MessagePreviewText;

/**
 * The quote a reply carries as `reply_to`, in the band chat (#1377) and in a conversation (#1380).
 *
 * @phpstan-type AnsweredMessage array{id: string, content: string, isDeleted: bool, hasImage: bool, hasVoiceNote: bool, authorId: string, authorUsername: string, authorIsDeleted: bool}
 * @phpstan-type ReplyQuote array{id: string, author_id: string, author_display_name: string, excerpt: string, has_image: bool, has_voice_note: bool, is_deleted: bool}
 */
readonly class ReplyQuoteBuilder
{
    private const int EXCERPT_MAX_LENGTH = 140;

    public function __construct(
        private MessagePreviewText $previewText,
    ) {
    }

    /**
     * @return AnsweredMessage
     */
    public function answeredOf(Message $answered): array
    {
        return [
            'id' => (string) $answered->id,
            'content' => $answered->content,
            'isDeleted' => $answered->isDeleted(),
            'hasImage' => $answered->imageFileId !== null,
            'hasVoiceNote' => $answered->isVoiceNote(),
            'authorId' => (string) $answered->author->id,
            'authorUsername' => $answered->author->username,
            'authorIsDeleted' => $answered->author->isDeleted(),
        ];
    }

    /**
     * A deleted message keeps its author, so the quote still says who was answered, and nothing of
     * what it said.
     *
     * @param AnsweredMessage       $answered
     * @param array<string, string> $mentionUsernamesById the answered message's mentions; a conversation has none
     *
     * @return ReplyQuote
     */
    public function build(array $answered, string $authorDisplayName, array $mentionUsernamesById, bool $isChannel): array
    {
        $isDeleted = $answered['isDeleted'];
        $excerpt = $isDeleted || $answered['content'] === ''
            ? ''
            : MessagePreviewText::shorten(
                $this->previewText->oneLine($answered['content'], $mentionUsernamesById, $isChannel),
                self::EXCERPT_MAX_LENGTH,
            );

        return [
            'id' => $answered['id'],
            'author_id' => $answered['authorId'],
            'author_display_name' => $authorDisplayName,
            'excerpt' => $excerpt,
            'has_image' => !$isDeleted && $answered['hasImage'],
            'has_voice_note' => !$isDeleted && $answered['hasVoiceNote'],
            'is_deleted' => $isDeleted,
        ];
    }
}

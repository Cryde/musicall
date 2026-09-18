<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageResource;
use App\ApiResource\Message\MessageThreadResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Service\BandSpace\ChatMentionRenderer;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class MessageBuilder
{
    public function __construct(
        #[Target('app.onlybr_sanitizer')]
        private HtmlSanitizerInterface $contentSanitizer,
        #[Target('app.plain_text_sanitizer')]
        private HtmlSanitizerInterface $previewSanitizer,
        private ChatMentionRenderer $chatMentionRenderer,
    ) {
    }

    /**
     * @param Message[] $entities
     *
     * @return MessageResource[]
     */
    public function buildList(array $entities): array
    {
        return array_map(
            fn (Message $entity): MessageResource => $this->buildItem($entity),
            $entities,
        );
    }

    /**
     * @param array<string, string> $mentionUsernamesById the names this message's mention rows resolve
     *                                                    to, from MessageMentionRepository. Only a
     *                                                    channel has any; a direct message cannot be
     *                                                    written with a mention at all.
     */
    public function buildItem(Message $entity, array $mentionUsernamesById = []): MessageResource
    {
        $dto = new MessageResource();
        $dto->id = (string) $entity->id;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->author = $entity->author;
        $dto->thread = $this->buildShallowThread($entity->thread->id);
        $dto->content = $this->renderMentions(
            $entity,
            $this->contentSanitizer->sanitize(nl2br($entity->content)),
            $mentionUsernamesById,
        );
        $dto->contentPreview = $this->toPreview($entity, $mentionUsernamesById);

        return $dto;
    }

    /**
     * Mentions go in after the sanitizer, never before, exactly as ChatMessageBuilder does it: by then
     * everything the sender typed is escaped, so the span is the only markup in the result.
     *
     * Only for a channel, and asked of the thread rather than of the map being empty: a member typing
     * a literal `@[...]` into a direct message means those characters, and answering them with a name
     * or with « inconnu » would be inventing content they did not write.
     *
     * @param array<string, string> $mentionUsernamesById
     */
    private function renderMentions(Message $entity, string $content, array $mentionUsernamesById): string
    {
        if (!$entity->thread->bandSpace instanceof BandSpace) {
            return $content;
        }

        return $this->chatMentionRenderer->render($content, $mentionUsernamesById);
    }

    /**
     * @param array<string, string> $mentionUsernamesById
     */
    private function toPreview(Message $entity, array $mentionUsernamesById): string
    {
        $content = $entity->thread->bandSpace instanceof BandSpace
            ? $this->chatMentionRenderer->renderPlain($entity->content, $mentionUsernamesById)
            : $entity->content;

        $text = html_entity_decode(
            $this->previewSanitizer->sanitize($content),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function buildShallowThread(UuidInterface|string|null $threadId): MessageThreadResource
    {
        $dto = new MessageThreadResource();
        $dto->id = (string) $threadId;

        return $dto;
    }
}

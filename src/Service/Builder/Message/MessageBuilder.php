<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageResource;
use App\ApiResource\Message\MessageThreadResource;
use App\Entity\Message\Message;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class MessageBuilder
{
    public function __construct(
        private HtmlSanitizerInterface $appOnlybrSanitizer,
        private HtmlSanitizerInterface $appPlainTextSanitizer,
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

    public function buildItem(Message $entity): MessageResource
    {
        $dto = new MessageResource();
        $dto->id = (string) $entity->id;
        $dto->creationDatetime = $entity->creationDatetime;
        $dto->author = $entity->author;
        $dto->thread = $this->buildShallowThread($entity->thread->id);
        $dto->content = $this->appOnlybrSanitizer->sanitize(nl2br($entity->content));
        $dto->contentPreview = $this->toPreview($entity->content);

        return $dto;
    }

    private function toPreview(string $content): string
    {
        $text = html_entity_decode(
            $this->appPlainTextSanitizer->sanitize($content),
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

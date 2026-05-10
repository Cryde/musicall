<?php

declare(strict_types=1);

namespace App\Service\Builder\Message;

use App\ApiResource\Message\MessageResource;
use App\ApiResource\Message\MessageThreadResource;
use App\Entity\Message\Message;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

readonly class MessageBuilder
{
    public function __construct(
        private HtmlSanitizerInterface $appOnlybrSanitizer,
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

        return $dto;
    }

    private function buildShallowThread(\Ramsey\Uuid\UuidInterface|string|null $threadId): MessageThreadResource
    {
        $dto = new MessageThreadResource();
        $dto->id = (string) $threadId;

        return $dto;
    }
}

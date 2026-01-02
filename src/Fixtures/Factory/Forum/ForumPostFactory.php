<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Forum;

use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Fixtures\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<ForumPost>
 */
final class ForumPostFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'content' => self::faker()->paragraphs(self::faker()->numberBetween(1, 4), true),
            'creationDatetime' => self::faker()->dateTimeBetween('-1 year', 'now'),
            'creator' => UserFactory::new(),
            'topic' => ForumTopicFactory::new(),
            'updateDatetime' => null,
        ];
    }

    /**
     * @param Proxy<ForumTopic>|ForumTopic $topic
     */
    public function withTopic(Proxy|ForumTopic $topic): self
    {
        return $this->with(['topic' => $topic]);
    }

    public static function class(): string
    {
        return ForumPost::class;
    }
}

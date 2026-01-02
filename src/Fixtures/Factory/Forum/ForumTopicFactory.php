<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Forum;

use App\Entity\Forum\Forum;
use App\Entity\Forum\ForumTopic;
use App\Fixtures\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentProxyObjectFactory<ForumTopic>
 */
final class ForumTopicFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        $title = self::faker()->sentence(6);

        return [
            'author' => UserFactory::new(),
            'creationDatetime' => self::faker()->dateTimeBetween('-1 year', 'now'),
            'forum' => ForumFactory::new(),
            'isLocked' => false,
            'postNumber' => 0,
            'slug' => self::faker()->slug(),
            'title' => $title,
            'type' => ForumTopic::TYPE_TOPIC_DEFAULT,
        ];
    }

    /**
     * @param Proxy<Forum>|Forum $forum
     */
    public function withForum(Proxy|Forum $forum): self
    {
        return $this->with(['forum' => $forum]);
    }

    public function asPinned(): self
    {
        return $this->with(['type' => ForumTopic::TYPE_TOPIC_PINNED]);
    }

    public function asLocked(): self
    {
        return $this->with(['isLocked' => true]);
    }

    public static function class(): string
    {
        return ForumTopic::class;
    }
}

<?php

namespace App\Tests\Unit\Service\Builder\Forum;

use App\Entity\Forum\Forum;
use App\Entity\User;
use App\Service\Builder\Forum\ForumTopicBuilder;
use PHPUnit\Framework\TestCase;

class ForumTopicBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $builder = new ForumTopicBuilder();

        $forum = (new Forum())->setTitle('forum_title');
        $author = (new User())->setId('user_id')->setUsername('user_username');
        $result = $builder->build($forum, $author, 'forum_topic_title');

        $this->assertSame('forum_topic_title', $result->getTitle());
        $this->assertSame('user_id', $result->getAuthor()->getId());
        $this->assertSame('forum_title', $result->getForum()->getTitle());
        $this->assertSame(0, $result->getType()); // ForumTopic::TYPE_TOPIC_DEFAULT
        $this->assertSame(false, $result->getIsLocked());
        $this->assertSame(null, $result->getLastPost());
        $this->assertSame(0, $result->getPostNumber());
    }
}
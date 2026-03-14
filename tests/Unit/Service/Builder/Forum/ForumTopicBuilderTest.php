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

        $forum = new Forum();
        $forum->title = 'forum_title';
        $author = new User();
        $author->id = 'user_id';
        $author->username = 'user_username';
        $result = $builder->build($forum, $author, 'forum_topic_title');

        $this->assertSame('forum_topic_title', $result->title);
        $this->assertSame('user_id', $result->author->id);
        $this->assertSame('forum_title', $result->forum->title);
        $this->assertSame(0, $result->type);
        $this->assertSame(false, $result->isLocked);
        $this->assertSame(null, $result->lastPost);
        $this->assertSame(0, $result->postNumber);
    }
}

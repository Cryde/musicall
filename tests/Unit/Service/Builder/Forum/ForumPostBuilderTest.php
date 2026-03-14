<?php

namespace App\Tests\Unit\Service\Builder\Forum;

use App\Entity\Forum\ForumTopic;
use App\Entity\User;
use App\Service\Builder\Forum\ForumPostBuilder;
use PHPUnit\Framework\TestCase;

class ForumPostBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $builder = new ForumPostBuilder();

        $forumTopic = new ForumTopic();
        $forumTopic->title = 'forum_topic';
        $author = new User();
        $author->id = 'user_id';
        $author->username = 'user_username';
        $result = $builder->build($forumTopic, $author, 'content_post');

        $this->assertSame('content_post', $result->content);
        $this->assertSame('user_id', $result->creator->id);
        $this->assertSame('user_username', $result->creator->getUserIdentifier());
        $this->assertSame('forum_topic', $result->topic->title);
    }
}

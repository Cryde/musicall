<?php

namespace App\Tests\Unit\Service\Builder\Forum;

use App\Entity\Forum\ForumTopic;
use App\Entity\User;
use App\Service\Builder\Forum\ForumPostBuilder;
use PHPUnit\Framework\TestCase;

class ForumPostBuilderTest extends TestCase
{
    public function testBuild()
    {
        $builder = new ForumPostBuilder();

        $forumTopic = (new ForumTopic())->setTitle('forum_topic');
        $author = (new User())->setId('user_id')->setUsername('user_username');
        $result = $builder->build($forumTopic, $author, 'content_post');

        $this->assertSame('content_post', $result->getContent());
        $this->assertSame('user_id', $result->getCreator()->getId());
        $this->assertSame('user_username', $result->getCreator()->getUserIdentifier());
        $this->assertSame('forum_topic', $result->getTopic()->getTitle());
    }
}
<?php declare(strict_types=1);

namespace App\Tests\Api\Admin\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RecentActivityTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_returns_empty_lists_when_no_data(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/forum/recent-activity');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/RecentActivity',
            '@id' => '/api/admin/forum/recent-activity',
            '@type' => 'RecentActivity',
            'recent_topics' => [],
            'recent_posts' => [],
        ]);
    }

    public function test_returns_latest_topics_and_posts_in_descending_order(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $author = UserFactory::new()->create(['username' => 'topic_author', 'email' => 'ta@test.com']);
        $poster = UserFactory::new()->create(['username' => 'post_creator', 'email' => 'pc@test.com']);

        $olderTopic = ForumTopicFactory::new([
            'author' => $author,
            'title' => 'Older topic',
            'slug' => 'older-topic',
            'creationDatetime' => new \DateTime('-2 days'),
        ])->create();
        $newerTopic = ForumTopicFactory::new([
            'author' => $author,
            'title' => 'Newer topic',
            'slug' => 'newer-topic',
            'creationDatetime' => new \DateTime('-1 day'),
        ])->create();

        ForumPostFactory::new([
            'topic' => $olderTopic,
            'creator' => $poster,
            'content' => '<p>An <strong>older</strong> reply with HTML</p>',
            'creationDatetime' => new \DateTime('-2 days'),
            'updateDatetime' => null,
        ])->create();
        ForumPostFactory::new([
            'topic' => $newerTopic,
            'creator' => $poster,
            'content' => str_repeat('A very long message. ', 20),
            'creationDatetime' => new \DateTime('-1 day'),
            'updateDatetime' => null,
        ])->create();

        $this->client->loginUser($admin->_real());
        $this->client->request('GET', '/api/admin/forum/recent-activity');

        $this->assertResponseIsSuccessful();

        $body = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(2, $body['recent_topics']);
        $this->assertSame('Newer topic', $body['recent_topics'][0]['title']);
        $this->assertSame('newer-topic', $body['recent_topics'][0]['slug']);
        $this->assertSame('topic_author', $body['recent_topics'][0]['author_username']);
        $this->assertSame('Older topic', $body['recent_topics'][1]['title']);

        $this->assertCount(2, $body['recent_posts']);
        $this->assertSame('newer-topic', $body['recent_posts'][0]['topic_slug']);
        $this->assertSame('Newer topic', $body['recent_posts'][0]['topic_title']);
        $this->assertSame('post_creator', $body['recent_posts'][0]['creator_username']);
        $this->assertSame(1, $body['recent_posts'][0]['topic_page']);

        // Excerpt is truncated to 120 chars (119 + ellipsis)
        $longExcerpt = $body['recent_posts'][0]['content_excerpt'];
        $this->assertStringEndsWith('…', $longExcerpt);
        $this->assertSame(120, mb_strlen($longExcerpt));

        // HTML is stripped from older post
        $this->assertSame('An older reply with HTML', $body['recent_posts'][1]['content_excerpt']);
    }

    public function test_topic_page_reflects_post_position(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $author = UserFactory::new()->create(['username' => 'author', 'email' => 'au@test.com']);
        $topic = ForumTopicFactory::new(['author' => $author, 'slug' => 'long-topic'])->create();

        // 25 older posts → the latest post is the 26th (page 3 with 10 per page)
        for ($i = 0; $i < 25; $i++) {
            ForumPostFactory::new([
                'topic' => $topic,
                'creator' => $author,
                'creationDatetime' => new \DateTime(sprintf('-%d hours', 100 - $i)),
                'updateDatetime' => null,
            ])->create();
        }
        ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $author,
            'content' => 'Latest reply',
            'creationDatetime' => new \DateTime('-1 minute'),
            'updateDatetime' => null,
        ])->create();

        $this->client->loginUser($admin->_real());
        $this->client->request('GET', '/api/admin/forum/recent-activity');

        $this->assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('Latest reply', $body['recent_posts'][0]['content_excerpt']);
        $this->assertSame(3, $body['recent_posts'][0]['topic_page']);
    }

    public function test_caps_at_ten_topics_and_ten_posts(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $author = UserFactory::new()->create(['username' => 'a', 'email' => 'a@test.com']);

        for ($i = 0; $i < 12; $i++) {
            ForumTopicFactory::new([
                'author' => $author,
                'title' => 'Topic ' . $i,
                'slug' => 'topic-' . $i,
                'creationDatetime' => new \DateTime('-' . $i . ' hours'),
            ])->create();
        }
        $topicForPosts = ForumTopicFactory::new(['author' => $author, 'slug' => 'host-topic'])->create();
        for ($i = 0; $i < 12; $i++) {
            ForumPostFactory::new([
                'topic' => $topicForPosts,
                'creator' => $author,
                'creationDatetime' => new \DateTime('-' . $i . ' hours'),
                'updateDatetime' => null,
            ])->create();
        }

        $this->client->loginUser($admin->_real());
        $this->client->request('GET', '/api/admin/forum/recent-activity');

        $this->assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(10, $body['recent_topics']);
        $this->assertCount(10, $body['recent_posts']);
    }

    public function test_forbidden_for_non_admin(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/forum/recent-activity');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

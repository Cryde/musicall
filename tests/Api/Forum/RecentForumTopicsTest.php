<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Entity\Forum\Forum;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The topics that moved last, across the forums, for the logged in home (#1078). */
#[ResetDatabase]
class RecentForumTopicsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_three_topics_that_moved_last_are_listed_with_their_forum(): void
    {
        $category = ForumCategoryFactory::new(['position' => 1])->create();
        $gear = ForumFactory::new(['forumCategory' => $category, 'title' => 'Matériel', 'slug' => 'materiel'])->create();
        $bands = ForumFactory::new(['forumCategory' => $category, 'title' => 'Groupes', 'slug' => 'groupes'])->create();

        $this->topic($gear, 'Quel ampli pour débuter', 'quel-ampli', 4, '2026-09-20 10:00:00');
        $this->topic($bands, 'Trouver un local', 'trouver-un-local', 1, '2026-09-25 10:00:00');
        $this->topic($gear, 'Cordes nylon ou acier', 'cordes', 2, '2026-09-22 10:00:00');
        // The oldest activity: left out of three.
        $this->topic($bands, 'Nom de groupe', 'nom-de-groupe', 7, '2026-09-01 10:00:00');

        $this->client->request('GET', '/api/forums/recent-topics');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/RecentForumTopics',
            '@id' => '/api/forums/recent-topics',
            '@type' => 'RecentForumTopics',
            'topics' => [
                [
                    '@type' => 'RecentForumTopic',
                    'title' => 'Trouver un local',
                    'slug' => 'trouver-un-local',
                    'forum_title' => 'Groupes',
                    'forum_slug' => 'groupes',
                    'replies' => 0,
                    'last_activity_datetime' => '2026-09-25T10:00:00+00:00',
                ],
                [
                    '@type' => 'RecentForumTopic',
                    'title' => 'Cordes nylon ou acier',
                    'slug' => 'cordes',
                    'forum_title' => 'Matériel',
                    'forum_slug' => 'materiel',
                    'replies' => 1,
                    'last_activity_datetime' => '2026-09-22T10:00:00+00:00',
                ],
                [
                    '@type' => 'RecentForumTopic',
                    'title' => 'Quel ampli pour débuter',
                    'slug' => 'quel-ampli',
                    'forum_title' => 'Matériel',
                    'forum_slug' => 'materiel',
                    'replies' => 3,
                    'last_activity_datetime' => '2026-09-20T10:00:00+00:00',
                ],
            ],
        ]);
    }

    public function test_a_topic_without_a_last_post_goes_by_its_creation(): void
    {
        $category = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new(['forumCategory' => $category, 'title' => 'Matériel', 'slug' => 'materiel'])->create();
        $this->topic($forum, 'Ancien sujet actif', 'ancien', 2, '2026-09-10 10:00:00');
        ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Sujet sans message',
            'slug' => 'sans-message',
            'postNumber' => 0,
            'author' => UserFactory::new()->create(),
            'creationDatetime' => new \DateTime('2026-09-20 10:00:00'),
        ])->create();

        $this->client->request('GET', '/api/forums/recent-topics');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/RecentForumTopics',
            '@id' => '/api/forums/recent-topics',
            '@type' => 'RecentForumTopics',
            'topics' => [
                [
                    '@type' => 'RecentForumTopic',
                    'title' => 'Sujet sans message',
                    'slug' => 'sans-message',
                    'forum_title' => 'Matériel',
                    'forum_slug' => 'materiel',
                    'replies' => 0,
                    'last_activity_datetime' => '2026-09-20T10:00:00+00:00',
                ],
                [
                    '@type' => 'RecentForumTopic',
                    'title' => 'Ancien sujet actif',
                    'slug' => 'ancien',
                    'forum_title' => 'Matériel',
                    'forum_slug' => 'materiel',
                    'replies' => 1,
                    'last_activity_datetime' => '2026-09-10T10:00:00+00:00',
                ],
            ],
        ]);
    }

    public function test_no_topic_is_an_empty_list(): void
    {
        $this->client->request('GET', '/api/forums/recent-topics');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/RecentForumTopics',
            '@id' => '/api/forums/recent-topics',
            '@type' => 'RecentForumTopics',
            'topics' => [],
        ]);
    }

    private function topic(Forum $forum, string $title, string $slug, int $posts, string $lastActivity): void
    {
        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => $title,
            'slug' => $slug,
            'postNumber' => $posts,
            'author' => UserFactory::new()->create(),
            'creationDatetime' => new \DateTime('2026-08-01 10:00:00'),
        ])->create();
        $topic->lastPost = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => UserFactory::new()->create(),
            'creationDatetime' => new \DateTime($lastActivity),
        ])->create();
        \Zenstruck\Foundry\Persistence\save($topic);
    }
}

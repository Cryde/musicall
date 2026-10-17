<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Entity\Forum\ForumTopic;
use App\Entity\Forum\ForumTopicParticipation;
use App\Entity\User;
use App\Repository\Forum\ForumTopicParticipationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumSourceFactory;
use App\Tests\Factory\Forum\ForumTopicParticipationFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ForumTopicParticipationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array UNAUTH_BODY = [
        'code' => 401,
        'message' => 'JWT Token not found',
    ];

    public function test_posting_creates_participation_for_poster(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/forum/posts',
            ['content' => 'my reply content here', 'topic' => '/api/forums/topics/' . $topic->slug],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();

        $repo = static::getContainer()->get(ForumTopicParticipationRepository::class);
        $participation = $repo->findOneByUserAndTopic($user, $topic);
        $this->assertNotNull($participation);
        $this->assertNotNull($participation->readDatetime, 'Poster should be marked as read');
        $this->assertNull($participation->removedDatetime);
    }

    public function test_new_post_flips_other_participants_to_unread(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $existingParticipant = UserFactory::new(['username' => 'existing', 'email' => 'existing@e.com'])->create();
        $newPoster = UserFactory::new(['username' => 'new', 'email' => 'new@e.com'])->create();
        // Seed with an old read time — newer last-post from a new poster must make this stale (unread)
        $this->seedParticipation($existingParticipant, $topic, readDatetime: new \DateTime('2020-01-01 00:00:00'));

        $this->client->loginUser($newPoster);
        $this->client->jsonRequest('POST', '/api/forum/posts',
            ['content' => 'new reply content here', 'topic' => '/api/forums/topics/' . $topic->slug],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();

        $this->assertFalse($this->isReadInDb($existingParticipant->id, $topic->id), 'Existing participant should be marked unread');
        $this->assertTrue($this->isReadInDb($newPoster->id, $topic->id), 'New poster should be marked read');
    }

    public function test_viewing_topic_marks_as_read(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedParticipation($user, $topic, readDatetime: null);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/forums/topics/' . $topic->slug, [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();

        $this->assertTrue($this->isReadInDb($user->id, $topic->id));
    }

    public function test_list_unauthenticated_returns_401(): void
    {
        $this->client->jsonRequest('GET', '/api/forums/topic-participations', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(self::UNAUTH_BODY);
    }

    public function test_list_returns_only_current_user_non_removed(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new(['username' => 'other', 'email' => 'other@e.com'])->create();
        $this->seedParticipation($user, $topic, readDatetime: new \DateTime());
        $this->seedParticipation($other, $topic, readDatetime: new \DateTime());

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/forums/topic-participations', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();
        $resp = $this->getResponseAsArray();
        $this->assertCount(1, $resp['member'], 'List should only return current user participations');
        $this->assertSame('t', $resp['member'][0]['topic']['slug']);
        $this->assertTrue($resp['member'][0]['is_read']);
    }

    public function test_list_keeps_the_topics_of_every_locale(): void
    {
        // A member's own topics, not a forum listing: an English page still lists the French ones.
        $user = UserFactory::new()->asBaseUser()->create();
        $author = UserFactory::new(['username' => 'topic_creator', 'email' => 'topic_creator@e.com'])->create();
        $authorId = $author->id;
        $englishTopic = $this->topicWithLastPost('en', 'gear', 'Gear', 'which-amp', 'Which amp', $author, '2026-09-25 10:00:00');
        $frenchTopic = $this->topicWithLastPost('fr', 'materiel', 'Matériel', 'quel-ampli', 'Quel ampli', $author, '2026-09-20 10:00:00');
        $englishParticipation = $this->seedParticipation($user, $englishTopic, readDatetime: new \DateTime('2026-09-26 10:00:00'));
        $frenchParticipation = $this->seedParticipation($user, $frenchTopic, readDatetime: new \DateTime('2026-09-21 10:00:00'));

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/forums/topic-participations', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_X_LOCALE' => 'en',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumTopicParticipation',
            '@id' => '/api/forums/topic-participations',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/forum_topic_participations/' . $englishParticipation->id,
                    '@type' => 'ForumTopicParticipation',
                    'id' => $englishParticipation->id,
                    'is_read' => true,
                    'creation_datetime' => $englishParticipation->creationDatetime->format('c'),
                    'topic' => [
                        '@type' => 'ForumTopicSummary',
                        'id' => $englishTopic->id,
                        'title' => 'Which amp',
                        'slug' => 'which-amp',
                        'is_locked' => false,
                        'is_resolved' => false,
                        'is_pinned' => false,
                        'post_number' => 1,
                        'creation_datetime' => '2026-09-25T10:00:00+00:00',
                        'last_post' => [
                            '@type' => 'ForumPost',
                            'id' => $englishTopic->lastPost?->id,
                            'creation_datetime' => '2026-09-25T10:00:00+00:00',
                            'creator' => [
                                '@type' => 'User',
                                'id' => $authorId,
                                'username' => 'topic_creator',
                                'display_name' => 'topic_creator',
                            ],
                        ],
                        'author' => [
                            '@type' => 'User',
                            'id' => $authorId,
                            'username' => 'topic_creator',
                            'display_name' => 'topic_creator',
                        ],
                        'forum' => [
                            '@type' => 'Forum',
                            'id' => $englishTopic->forum->id,
                            'title' => 'Gear',
                            'slug' => 'gear',
                            'description' => 'Gear description',
                        ],
                    ],
                ],
                [
                    '@id' => '/api/forum_topic_participations/' . $frenchParticipation->id,
                    '@type' => 'ForumTopicParticipation',
                    'id' => $frenchParticipation->id,
                    'is_read' => true,
                    'creation_datetime' => $frenchParticipation->creationDatetime->format('c'),
                    'topic' => [
                        '@type' => 'ForumTopicSummary',
                        'id' => $frenchTopic->id,
                        'title' => 'Quel ampli',
                        'slug' => 'quel-ampli',
                        'is_locked' => false,
                        'is_resolved' => false,
                        'is_pinned' => false,
                        'post_number' => 1,
                        'creation_datetime' => '2026-09-20T10:00:00+00:00',
                        'last_post' => [
                            '@type' => 'ForumPost',
                            'id' => $frenchTopic->lastPost?->id,
                            'creation_datetime' => '2026-09-20T10:00:00+00:00',
                            'creator' => [
                                '@type' => 'User',
                                'id' => $authorId,
                                'username' => 'topic_creator',
                                'display_name' => 'topic_creator',
                            ],
                        ],
                        'author' => [
                            '@type' => 'User',
                            'id' => $authorId,
                            'username' => 'topic_creator',
                            'display_name' => 'topic_creator',
                        ],
                        'forum' => [
                            '@type' => 'Forum',
                            'id' => $frenchTopic->forum->id,
                            'title' => 'Matériel',
                            'slug' => 'materiel',
                            'description' => 'Matériel description',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_list_filters_out_removed(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedParticipation($user, $topic, readDatetime: new \DateTime(), removedDatetime: new \DateTime());

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/forums/topic-participations', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseIsSuccessful();
        $resp = $this->getResponseAsArray();
        $this->assertCount(0, $resp['member']);
    }

    public function test_remove_endpoint_hides_participation(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $user = UserFactory::new()->asBaseUser()->create();
        $participation = $this->seedParticipation($user, $topic, readDatetime: new \DateTime());

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/forums/topic-participations/' . $participation->id . '/remove', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertNotNull($this->removedDatetimeInDb($participation->id));
    }

    public function test_restore_endpoint_makes_visible_again(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $user = UserFactory::new()->asBaseUser()->create();
        $participation = $this->seedParticipation($user, $topic, readDatetime: new \DateTime(), removedDatetime: new \DateTime());

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/forums/topic-participations/' . $participation->id . '/restore', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertNull($this->removedDatetimeInDb($participation->id));
    }

    public function test_remove_other_users_participation_returns_403(): void
    {
        [, $topic] = $this->prepareTopicByOther();
        $owner = UserFactory::new(['username' => 'owner', 'email' => 'owner@e.com'])->create();
        $attacker = UserFactory::new(['username' => 'attacker', 'email' => 'attacker@e.com'])->create();
        $participation = $this->seedParticipation($owner, $topic, readDatetime: new \DateTime());

        $this->client->loginUser($attacker);
        $this->client->jsonRequest('POST', '/api/forums/topic-participations/' . $participation->id . '/remove', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->assertNull($this->removedDatetimeInDb($participation->id));
    }

    public function test_remove_unknown_id_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/forums/topic-participations/00000000-0000-0000-0000-000000000000/remove', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array{0: User, 1: ForumTopic}
     */
    private function prepareTopicByOther(): array
    {
        $other = UserFactory::new(['username' => 'topic_creator', 'email' => 'topic_creator@e.com'])->create();
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumCategory = ForumCategoryFactory::new(['position' => 1, 'forumSource' => $forumSource])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory])->create();
        $topic = ForumTopicFactory::new([
            'author' => $other,
            'forum' => $forum,
            'slug' => 't',
            'title' => 'T',
        ])->create();

        return [$other, $topic];
    }

    /**
     * Mirrors the builder's is_read logic for DB-level verification in tests.
     */
    private function isReadInDb(string $userId, string $topicId): ?bool
    {
        $conn = static::getContainer()->get('doctrine')->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT p.read_datetime, lp.creation_datetime AS last_post_datetime
             FROM forum_topic_participation p
             INNER JOIN forum_topic t ON t.id = p.topic_id
             LEFT JOIN forum_post lp ON lp.id = t.last_post_id
             WHERE p.user_id = :user AND p.topic_id = :topic',
            ['user' => $userId, 'topic' => $topicId]
        );
        if ($row === false) {
            return null;
        }
        if ($row['read_datetime'] === null) {
            return false;
        }
        if ($row['last_post_datetime'] === null) {
            return true;
        }

        return $row['read_datetime'] >= $row['last_post_datetime'];
    }

    private function removedDatetimeInDb(string $participationId): ?string
    {
        $conn = static::getContainer()->get('doctrine')->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT removed_datetime FROM forum_topic_participation WHERE id = :id',
            ['id' => $participationId]
        );

        return $row === false ? null : ($row['removed_datetime'] ?? null);
    }

    private function topicWithLastPost(
        string $locale,
        string $forumSlug,
        string $forumTitle,
        string $topicSlug,
        string $topicTitle,
        User $author,
        string $lastPostDatetime,
    ): ForumTopic {
        $forumCategory = ForumCategoryFactory::new(['position' => 1, 'locale' => $locale])->create();
        $forum = ForumFactory::new([
            'forumCategory' => $forumCategory,
            'slug' => $forumSlug,
            'title' => $forumTitle,
            'description' => $forumTitle . ' description',
        ])->create();
        $topic = ForumTopicFactory::new([
            'author' => $author,
            'forum' => $forum,
            'slug' => $topicSlug,
            'title' => $topicTitle,
            'postNumber' => 1,
            'creationDatetime' => new \DateTime($lastPostDatetime),
        ])->create();
        $topic->lastPost = ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $author,
            'creationDatetime' => new \DateTime($lastPostDatetime),
        ])->create();
        \Zenstruck\Foundry\Persistence\save($topic);

        return $topic;
    }

    private function seedParticipation(
        User $user,
        ForumTopic $topic,
        ?\DateTimeInterface $readDatetime = null,
        ?\DateTimeInterface $removedDatetime = null,
    ): ForumTopicParticipation {
        return ForumTopicParticipationFactory::new([
            'user' => $user,
            'topic' => $topic,
            'readDatetime' => $readDatetime,
            'removedDatetime' => $removedDatetime,
        ])->create();
    }
}

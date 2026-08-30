<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Forum;

use App\Entity\Forum\ForumPost;
use App\Repository\Forum\ForumPostReportRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumPostReportFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminForumReportTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array SERVER_PARAMS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_list_unauthenticated_returns_401(): void
    {
        $this->client->jsonRequest('GET', '/api/admin/forum/reports', [], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_list_as_base_user_returns_403(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/admin/forum/reports', [], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_list_returns_empty_collection_when_nothing_is_pending(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('GET', '/api/admin/forum/reports', [], self::SERVER_PARAMS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminForumReport',
            '@id' => '/api/admin/forum/reports',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_list_returns_pending_reports_oldest_first(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $post = $this->createPost('<p>Un message <strong>problématique</strong></p>');
        $firstReporter = UserFactory::new()->create(['username' => 'first_reporter', 'email' => 'first@email.com']);
        $secondReporter = UserFactory::new()->create(['username' => 'second_reporter', 'email' => 'second@email.com']);

        $older = ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => $firstReporter,
            'reason' => 'Propos insultants',
            'creationDatetime' => new \DateTime('2026-08-01T10:00:00+00:00'),
        ])->create();
        $newer = ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => $secondReporter,
            'reason' => 'Spam publicitaire',
            'creationDatetime' => new \DateTime('2026-08-02T11:30:00+00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('GET', '/api/admin/forum/reports', [], self::SERVER_PARAMS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminForumReport',
            '@id' => '/api/admin/forum/reports',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/admin_forum_reports/' . $older->id,
                    '@type' => 'AdminForumReport',
                    'id' => (string) $older->id,
                    'reason' => 'Propos insultants',
                    'creation_datetime' => '2026-08-01T10:00:00+00:00',
                    'reporter_username' => 'first_reporter',
                    'post_id' => (string) $post->id,
                    'post_excerpt' => 'Un message problématique',
                    'post_author_username' => 'post_author',
                    'topic_slug' => 'test-topic',
                    'topic_title' => 'Test Topic',
                    'topic_page' => 1,
                ],
                [
                    '@id' => '/api/admin_forum_reports/' . $newer->id,
                    '@type' => 'AdminForumReport',
                    'id' => (string) $newer->id,
                    'reason' => 'Spam publicitaire',
                    'creation_datetime' => '2026-08-02T11:30:00+00:00',
                    'reporter_username' => 'second_reporter',
                    'post_id' => (string) $post->id,
                    'post_excerpt' => 'Un message problématique',
                    'post_author_username' => 'post_author',
                    'topic_slug' => 'test-topic',
                    'topic_title' => 'Test Topic',
                    'topic_page' => 1,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_list_excludes_resolved_reports(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $post = $this->createPost('Un message signalé deux fois');
        $pendingReporter = UserFactory::new()->create(['username' => 'pending_reporter', 'email' => 'pending@email.com']);
        $doneReporter = UserFactory::new()->create(['username' => 'done_reporter', 'email' => 'done@email.com']);

        $pending = ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => $pendingReporter,
            'reason' => 'Toujours en attente',
            'creationDatetime' => new \DateTime('2026-08-03T09:00:00+00:00'),
        ])->create();
        ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => $doneReporter,
            'reason' => 'Déjà traité',
            'creationDatetime' => new \DateTime('2026-08-01T09:00:00+00:00'),
            'resolvedDatetime' => new \DateTime('2026-08-02T09:00:00+00:00'),
            'resolvedBy' => $admin,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('GET', '/api/admin/forum/reports', [], self::SERVER_PARAMS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminForumReport',
            '@id' => '/api/admin/forum/reports',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/admin_forum_reports/' . $pending->id,
                    '@type' => 'AdminForumReport',
                    'id' => (string) $pending->id,
                    'reason' => 'Toujours en attente',
                    'creation_datetime' => '2026-08-03T09:00:00+00:00',
                    'reporter_username' => 'pending_reporter',
                    'post_id' => (string) $post->id,
                    'post_excerpt' => 'Un message signalé deux fois',
                    'post_author_username' => 'post_author',
                    'topic_slug' => 'test-topic',
                    'topic_title' => 'Test Topic',
                    'topic_page' => 1,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_list_reports_the_page_the_post_sits_on(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $post = $this->createPost('Le vingt-cinquième message');
        for ($i = 1; $i <= 24; $i++) {
            ForumPostFactory::new([
                'topic' => $post->topic,
                'creator' => $post->creator,
                'content' => 'Message ' . $i,
                'creationDatetime' => new \DateTime(sprintf('2026-08-01T10:%02d:00+00:00', $i)),
                'updateDatetime' => null,
            ])->create();
        }

        $report = ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => UserFactory::new(['username' => 'page_reporter', 'email' => 'page@email.com']),
            'reason' => 'Hors sujet',
            'creationDatetime' => new \DateTime('2026-08-04T09:00:00+00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('GET', '/api/admin/forum/reports', [], self::SERVER_PARAMS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminForumReport',
            '@id' => '/api/admin/forum/reports',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/admin_forum_reports/' . $report->id,
                    '@type' => 'AdminForumReport',
                    'id' => (string) $report->id,
                    'reason' => 'Hors sujet',
                    'creation_datetime' => '2026-08-04T09:00:00+00:00',
                    'reporter_username' => 'page_reporter',
                    'post_id' => (string) $post->id,
                    'post_excerpt' => 'Le vingt-cinquième message',
                    'post_author_username' => 'post_author',
                    'topic_slug' => 'test-topic',
                    'topic_title' => 'Test Topic',
                    'topic_page' => 3,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_resolve_unauthenticated_returns_401(): void
    {
        $report = $this->createReport();

        $this->client->jsonRequest('POST', '/api/admin/forum/reports/' . $report->id . '/resolve', [], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
        $this->assertNull($this->reportRepository()->find($report->id)->resolvedDatetime);
    }

    public function test_resolve_as_base_user_returns_403(): void
    {
        $report = $this->createReport();
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/admin/forum/reports/' . $report->id . '/resolve', [], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
        ]);
        $this->assertNull($this->reportRepository()->find($report->id)->resolvedDatetime);
    }

    public function test_resolve_unknown_report_returns_404(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/admin/forum/reports/00000000-0000-0000-0000-000000000000/resolve',
            [],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Signalement inexistant',
            'description' => 'Signalement inexistant',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    /**
     * A malformed id is not a missing row, it is a string the uuid type cannot convert at all. Handing it
     * to find() throws before the 404 above can be reached, so garbage in the URL would answer 500.
     */
    public function test_resolve_with_a_malformed_report_id_returns_404(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'POST',
            '/api/admin/forum/reports/not-a-uuid-at-all/resolve',
            [],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Signalement inexistant',
            'description' => 'Signalement inexistant',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_resolve_marks_the_report_resolved_and_records_the_moderator(): void
    {
        $report = $this->createReport();
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/forum/reports/' . $report->id . '/resolve', [], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $resolved = $this->reportRepository()->find($report->id);
        $this->assertTrue($resolved->isResolved());
        $this->assertNotNull($resolved->resolvedDatetime);
        $this->assertSame($admin->id, $resolved->resolvedBy->id);
    }

    public function test_resolve_an_already_resolved_report_returns_409(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $report = ForumPostReportFactory::new([
            'post' => $this->createPost('Un message signalé'),
            'reporter' => UserFactory::new(['username' => 'reporter', 'email' => 'reporter@email.com']),
            'reason' => 'Déjà traité',
            'resolvedDatetime' => new \DateTime('2026-08-02T09:00:00+00:00'),
            'resolvedBy' => $admin,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/forum/reports/' . $report->id . '/resolve', [], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce signalement est déjà résolu',
            'description' => 'Ce signalement est déjà résolu',
            'status' => 409,
            'type' => '/errors/409',
        ]);
        $this->assertSame(
            '2026-08-02T09:00:00+00:00',
            $this->reportRepository()->find($report->id)->resolvedDatetime->format('c')
        );
    }

    private function reportRepository(): ForumPostReportRepository
    {
        /** @var ForumPostReportRepository $repository */
        $repository = static::getContainer()->get(ForumPostReportRepository::class);

        return $repository;
    }

    private function createReport(): \App\Entity\Forum\ForumPostReport
    {
        return ForumPostReportFactory::new([
            'post' => $this->createPost('Un message signalé'),
            'reporter' => UserFactory::new(['username' => 'reporter', 'email' => 'reporter@email.com']),
            'reason' => 'Propos insultants',
            'creationDatetime' => new \DateTime('2026-08-01T10:00:00+00:00'),
        ])->create();
    }

    private function createPost(string $content): ForumPost
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1])->create();
        $forum = ForumFactory::new(['forumCategory' => $forumCategory])->create();
        $author = UserFactory::new(['username' => 'post_author', 'email' => 'post_author@email.com'])->create();
        $topic = ForumTopicFactory::new([
            'forum' => $forum,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'author' => $author,
        ])->create();

        return ForumPostFactory::new([
            'topic' => $topic,
            'creator' => $author,
            'content' => $content,
            'creationDatetime' => new \DateTime('2026-08-01T12:00:00+00:00'),
            'updateDatetime' => null,
        ])->create();
    }
}

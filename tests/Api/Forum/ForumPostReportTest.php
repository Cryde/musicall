<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

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
class ForumPostReportTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array SERVER_PARAMS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_report_unauthenticated_returns_401(): void
    {
        $post = $this->createPost();

        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => 'Ce message est insultant'],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
        $this->assertSame(0, $this->reportRepository()->count([]));
    }

    public function test_report_unknown_post_returns_404(): void
    {
        $reporter = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/00000000-0000-0000-0000-000000000000/report',
            ['reason' => 'Ce message est insultant'],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message de forum inexistant',
            'description' => 'Message de forum inexistant',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    /**
     * A malformed id is not a missing row, it is a string the uuid type cannot convert at all. Handing it
     * to find() throws before the 404 above can be reached, so garbage in the URL would answer 500.
     */
    public function test_report_with_a_malformed_post_id_returns_404(): void
    {
        $reporter = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/not-a-uuid-at-all/report',
            ['reason' => 'Ce message est insultant'],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message de forum inexistant',
            'description' => 'Message de forum inexistant',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_report_with_empty_reason_returns_422(): void
    {
        $post = $this->createPost();
        $reporter = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => ''],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'reason',
                    'message' => 'Veuillez préciser le motif du signalement',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'reason: Veuillez préciser le motif du signalement',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'reason: Veuillez préciser le motif du signalement',
        ]);
        $this->assertSame(0, $this->reportRepository()->count([]));
    }

    public function test_report_with_whitespace_only_reason_returns_422(): void
    {
        $post = $this->createPost();
        $reporter = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => '     '],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'reason',
                    'message' => 'Veuillez préciser le motif du signalement',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'reason: Veuillez préciser le motif du signalement',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'reason: Veuillez préciser le motif du signalement',
        ]);
        $this->assertSame(0, $this->reportRepository()->count([]));
    }

    public function test_report_with_reason_over_500_characters_returns_422(): void
    {
        $post = $this->createPost();
        $reporter = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => str_repeat('a', 501)],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'reason',
                    'message' => 'Le motif ne peut pas dépasser 500 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'reason: Le motif ne peut pas dépasser 500 caractères',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => 'reason: Le motif ne peut pas dépasser 500 caractères',
        ]);
        $this->assertSame(0, $this->reportRepository()->count([]));
    }

    public function test_report_with_reason_of_exactly_500_characters_succeeds(): void
    {
        $post = $this->createPost();
        $reporter = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => str_repeat('a', 500)],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $report = $this->reportRepository()->findOneBy([]);
        $this->assertNotNull($report);
        $this->assertSame(str_repeat('a', 500), $report->reason);
    }

    public function test_report_by_authenticated_user_persists_the_report(): void
    {
        $post = $this->createPost();
        $reporter = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => '  Ce message est insultant  '],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $report = $this->reportRepository()->findOneBy([]);
        $this->assertNotNull($report);
        $this->assertSame('Ce message est insultant', $report->reason);
        $this->assertSame((string) $post->id, (string) $report->post->id);
        $this->assertSame($reporter->id, $report->reporter->id);
        $this->assertNull($report->resolvedDatetime);
        $this->assertNull($report->resolvedBy);
        $this->assertFalse($report->isResolved());
    }

    public function test_report_same_post_twice_returns_409(): void
    {
        $post = $this->createPost();
        $reporter = UserFactory::new()->asBaseUser()->create();
        ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => $reporter,
            'reason' => 'Premier signalement',
        ])->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => 'Second signalement'],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous avez déjà signalé ce message',
            'description' => 'Vous avez déjà signalé ce message',
            'status' => 409,
            'type' => '/errors/409',
        ]);
        $this->assertSame(1, $this->reportRepository()->count([]));
        $this->assertSame('Premier signalement', $this->reportRepository()->findOneBy([])->reason);
    }

    public function test_report_already_resolved_post_by_same_user_still_returns_409(): void
    {
        $post = $this->createPost();
        $reporter = UserFactory::new()->asBaseUser()->create();
        ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => $reporter,
            'reason' => 'Signalement traité',
            'resolvedDatetime' => new \DateTime('-1 day'),
        ])->create();

        $this->client->loginUser($reporter);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => 'Encore un signalement'],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous avez déjà signalé ce message',
            'description' => 'Vous avez déjà signalé ce message',
            'status' => 409,
            'type' => '/errors/409',
        ]);
        $this->assertSame(1, $this->reportRepository()->count([]));
    }

    public function test_two_different_users_can_report_the_same_post(): void
    {
        $post = $this->createPost();
        $first = UserFactory::new()->create(['username' => 'first_reporter', 'email' => 'first@email.com']);
        $second = UserFactory::new()->asBaseUser()->create();
        ForumPostReportFactory::new([
            'post' => $post,
            'reporter' => $first,
            'reason' => 'Signalement du premier',
        ])->create();

        $this->client->loginUser($second);
        $this->client->jsonRequest(
            'POST',
            '/api/forums/posts/' . $post->id . '/report',
            ['reason' => 'Signalement du second'],
            self::SERVER_PARAMS
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(2, $this->reportRepository()->count([]));
        $this->assertNotNull($this->reportRepository()->findOneByPostAndReporter($post, $second));
    }

    private function reportRepository(): ForumPostReportRepository
    {
        /** @var ForumPostReportRepository $repository */
        $repository = static::getContainer()->get(ForumPostReportRepository::class);

        return $repository;
    }

    private function createPost(): ForumPost
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
            'content' => 'original content here',
            'updateDatetime' => null,
        ])->create();
    }
}

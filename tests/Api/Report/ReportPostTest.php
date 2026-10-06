<?php

declare(strict_types=1);

namespace App\Tests\Api\Report;

use App\Entity\Publication;
use App\Entity\Report\Report;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Report\ReportReason;
use App\Enum\Report\ReportTargetType;
use App\Repository\Report\ReportRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Comment\CommentFactory;
use App\Tests\Factory\Comment\CommentThreadFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileMediaFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Report\ReportFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ReportPostTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    private const array NOT_FOUND = [
        '@context' => '/api/contexts/Error',
        '@id' => '/api/errors/404',
        '@type' => 'Error',
        'title' => 'An error occurred',
        'detail' => 'Contenu introuvable',
        'status' => 404,
        'type' => '/errors/404',
        'description' => 'Contenu introuvable',
    ];

    public function test_unauthenticated(): void
    {
        $this->client->jsonRequest('POST', '/api/reports', ['target_type' => 'user', 'target_id' => 'x', 'reason' => 'spam'], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_reports_a_user_with_a_snapshot_of_their_profile(): void
    {
        $reporter = $this->reporter();
        $target = UserFactory::new()->create(['username' => 'spammer', 'email' => 'spammer@test.com']);
        $target->profile->displayName = 'Promo Followers';
        $target->profile->bio = 'Achetez mes followers';
        \Zenstruck\Foundry\Persistence\save($target);

        $this->report($reporter, 'user', (string) $target->id, 'spam', '  Il spamme tout le monde  ');

        $this->assertNoContent();
        $report = $this->onlyReport();
        $this->assertSame(ReportTargetType::User, $report->targetType);
        $this->assertSame($target->id, $report->targetAuthor?->id);
        $this->assertSame(ReportReason::Spam, $report->reason);
        $this->assertSame('Il spamme tout le monde', $report->details);
        $this->assertSame('Promo Followers Achetez mes followers', $report->snapshotText);
        $this->assertSame(['username' => 'spammer'], $report->snapshotContext);
    }

    public function test_reports_an_announce(): void
    {
        $reporter = $this->reporter();
        $announce = MusicianAnnounceFactory::new()->create(['note' => 'Arnaque garantie', 'locationName' => 'Lyon']);

        $this->report($reporter, 'announce', (string) $announce->id, 'fake');

        $this->assertNoContent();
        $report = $this->onlyReport();
        $this->assertSame('Arnaque garantie', $report->snapshotText);
        $this->assertSame($announce->author->id, $report->targetAuthor?->id);
    }

    public function test_reports_a_direct_message_from_its_conversation(): void
    {
        $reporter = $this->reporter();
        $sender = UserFactory::new()->create(['username' => 'harceleur', 'email' => 'harceleur@test.com']);
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $reporter])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $sender])->create();
        $message = MessageFactory::new(['thread' => $thread, 'author' => $sender, 'content' => 'Message insultant'])->create();

        $this->report($reporter, 'message', (string) $message->id, 'harassment');

        $this->assertNoContent();
        $report = $this->onlyReport();
        $this->assertSame('Message insultant', $report->snapshotText);
        $this->assertSame(['thread_id' => (string) $thread->id], $report->snapshotContext);
    }

    public function test_a_direct_message_of_someone_elses_conversation_is_not_found(): void
    {
        $reporter = $this->reporter();
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread])->create();
        $message = MessageFactory::new(['thread' => $thread, 'content' => 'Privé'])->create();

        $this->report($reporter, 'message', (string) $message->id, 'harassment');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals(self::NOT_FOUND);
    }

    public function test_reports_a_band_chat_message_as_a_member(): void
    {
        $reporter = $this->reporter();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $reporter])->create();
        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
        $message = MessageFactory::new(['thread' => $channel, 'content' => 'Propos haineux'])->create();

        $this->report($reporter, 'band_chat_message', (string) $message->id, 'inappropriate');

        $this->assertNoContent();
        $this->assertSame(
            ['band_space_id' => (string) $bandSpace->id, 'band_space_name' => 'The Rockers'],
            $this->onlyReport()->snapshotContext,
        );
    }

    public function test_a_band_chat_message_of_a_band_one_has_left_is_not_found(): void
    {
        $reporter = $this->reporter();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $reporter, 'status' => MembershipStatus::Left])->create();
        $channel = MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
        $message = MessageFactory::new(['thread' => $channel, 'content' => 'Interne'])->create();

        $this->report($reporter, 'band_chat_message', (string) $message->id, 'inappropriate');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals(self::NOT_FOUND);
    }

    public function test_reports_a_profile_media(): void
    {
        $reporter = $this->reporter();
        $owner = UserFactory::new()->create(['username' => 'guitariste', 'email' => 'guitariste@test.com']);
        $media = MusicianProfileMediaFactory::new([
            'musicianProfile' => MusicianProfileFactory::new(['user' => $owner]),
            'title' => 'Vidéo choquante',
        ])->create();

        $this->report($reporter, 'profile_media', (string) $media->id, 'inappropriate');

        $this->assertNoContent();
        $report = $this->onlyReport();
        $this->assertSame('Vidéo choquante https://www.youtube.com/watch?v=dQw4w9WgXcQ', $report->snapshotText);
        $this->assertSame(['profile' => 'musician', 'username' => 'guitariste', 'platform' => 'youtube'], $report->snapshotContext);
    }

    public function test_reports_a_forum_post_with_its_page(): void
    {
        $reporter = $this->reporter();
        $topic = ForumTopicFactory::new(['slug' => 'mon-sujet', 'title' => 'Mon sujet'])->create();
        $post = ForumPostFactory::new(['topic' => $topic, 'content' => '<p>Pub pour <b>mon site</b></p>'])->create();

        $this->report($reporter, 'forum_post', (string) $post->id, 'spam');

        $this->assertNoContent();
        $report = $this->onlyReport();
        $this->assertSame('Pub pour mon site', $report->snapshotText);
        $this->assertSame(['topic_slug' => 'mon-sujet', 'topic_title' => 'Mon sujet', 'topic_page' => 1], $report->snapshotContext);
    }

    public function test_reports_a_comment_on_an_online_publication(): void
    {
        $reporter = $this->reporter();
        $thread = CommentThreadFactory::new()->create();
        $publication = $this->onlinePublication(['thread' => $thread, 'slug' => 'ma-chronique', 'title' => 'Ma chronique']);
        $comment = CommentFactory::new(['thread' => $thread, 'content' => 'Commentaire injurieux'])->create();

        $this->report($reporter, 'comment', (string) $comment->id, 'harassment');

        $this->assertNoContent();
        $this->assertSame(
            ['publication_slug' => 'ma-chronique', 'publication_title' => 'Ma chronique', 'is_course' => false],
            $this->onlyReport()->snapshotContext,
        );
        $this->assertSame(Publication::STATUS_ONLINE, $publication->status);
    }

    public function test_reports_an_online_publication(): void
    {
        $reporter = $this->reporter();
        $publication = $this->onlinePublication(['slug' => 'video-douteuse', 'title' => 'Vidéo douteuse', 'shortDescription' => 'Contenu choquant']);

        $this->report($reporter, 'publication', (string) $publication->id, 'inappropriate');

        $this->assertNoContent();
        $this->assertSame('Vidéo douteuse Contenu choquant', $this->onlyReport()->snapshotText);
    }

    public function test_a_publication_that_is_not_online_is_not_found(): void
    {
        $reporter = $this->reporter();
        $draft = PublicationFactory::new(['status' => Publication::STATUS_DRAFT, 'subCategory' => PublicationSubCategoryFactory::new()->asChronique()])->create();

        $this->report($reporter, 'publication', (string) $draft->id, 'spam');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals(self::NOT_FOUND);
    }

    public function test_a_deleted_user_is_not_found(): void
    {
        $reporter = $this->reporter();
        $departed = UserFactory::new()->create([
            'username' => 'deleted_c7c9f2e1',
            'email' => 'deleted_c7c9f2e1@email.com',
            'deletionDatetime' => new \DateTimeImmutable('2026-06-01'),
        ]);

        $this->report($reporter, 'user', (string) $departed->id, 'spam');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals(self::NOT_FOUND);
    }

    public function test_a_malformed_id_is_not_found_rather_than_a_server_error(): void
    {
        $reporter = $this->reporter();

        $this->report($reporter, 'forum_post', 'not-a-uuid', 'spam');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals(self::NOT_FOUND);
    }

    public function test_an_unknown_type_and_reason_are_refused(): void
    {
        $reporter = $this->reporter();

        $this->report($reporter, 'galaxy', 'x', 'boredom');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=' . Choice::NO_SUCH_CHOICE_ERROR . ';1=' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'target_type', 'message' => 'Type de contenu inconnu', 'code' => Choice::NO_SUCH_CHOICE_ERROR],
                ['propertyPath' => 'reason', 'message' => 'Motif inconnu', 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => "target_type: Type de contenu inconnu\nreason: Motif inconnu",
            'description' => "target_type: Type de contenu inconnu\nreason: Motif inconnu",
            'type' => '/validation_errors/0=' . Choice::NO_SUCH_CHOICE_ERROR . ';1=' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_repeat_while_pending_changes_nothing(): void
    {
        $reporter = $this->reporter();
        $target = UserFactory::new()->create(['username' => 'spammer', 'email' => 'spammer@test.com']);
        ReportFactory::new(['reporter' => $reporter, 'targetType' => ReportTargetType::User, 'targetId' => (string) $target->id])->create();

        $this->report($reporter, 'user', (string) $target->id, 'harassment');

        $this->assertNoContent();
        $this->assertSame(1, self::getContainer()->get(ReportRepository::class)->count());
    }

    /** Another spelling of the same target is still the same target. */
    public function test_a_repeat_under_another_spelling_of_the_id_changes_nothing(): void
    {
        $reporter = $this->reporter();
        $target = UserFactory::new()->create(['username' => 'spammer', 'email' => 'spammer@test.com']);
        ReportFactory::new(['reporter' => $reporter, 'targetType' => ReportTargetType::User, 'targetId' => (string) $target->id])->create();

        $this->report($reporter, 'user', mb_strtoupper((string) $target->id), 'harassment');

        $this->assertNoContent();
        $this->assertSame(1, self::getContainer()->get(ReportRepository::class)->count());
    }

    public function test_a_comment_id_with_leading_zeros_is_stored_canonically(): void
    {
        $reporter = $this->reporter();
        $thread = CommentThreadFactory::new()->create();
        $this->onlinePublication(['thread' => $thread, 'slug' => 'ma-chronique', 'title' => 'Ma chronique']);
        $comment = CommentFactory::new(['thread' => $thread, 'content' => 'Commentaire injurieux'])->create();

        $this->report($reporter, 'comment', '000' . $comment->id, 'harassment');

        $this->assertNoContent();
        $this->assertSame((string) $comment->id, $this->onlyReport()->targetId);
    }

    public function test_reporting_again_after_a_resolution_opens_a_new_report(): void
    {
        $reporter = $this->reporter();
        $target = UserFactory::new()->create(['username' => 'spammer', 'email' => 'spammer@test.com']);
        ReportFactory::new([
            'reporter' => $reporter,
            'targetType' => ReportTargetType::User,
            'targetId' => (string) $target->id,
            'resolutionDatetime' => new \DateTimeImmutable('2026-09-01'),
        ])->create();

        $this->report($reporter, 'user', (string) $target->id, 'harassment');

        $this->assertNoContent();
        $this->assertSame(2, self::getContainer()->get(ReportRepository::class)->count());
    }

    public function test_reporting_oneself_changes_nothing(): void
    {
        $reporter = $this->reporter();

        $this->report($reporter, 'user', (string) $reporter->id, 'spam');

        $this->assertNoContent();
        $this->assertSame(0, self::getContainer()->get(ReportRepository::class)->count());
    }

    public function test_reporting_is_rate_limited(): void
    {
        $reporter = $this->reporter();
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.report_create');
        $limiter->create($reporter->getUserIdentifier())->consume(10);

        $this->report($reporter, 'user', (string) $reporter->id, 'spam');

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);
    }

    private function reporter(): object
    {
        return UserFactory::new()->asBaseUser()->create();
    }

    private function report(object $reporter, string $type, string $id, string $reason, ?string $details = null): void
    {
        $this->client->loginUser($reporter);
        $this->client->jsonRequest('POST', '/api/reports', array_filter([
            'target_type' => $type,
            'target_id' => $id,
            'reason' => $reason,
            'details' => $details,
        ], static fn (?string $value): bool => $value !== null), self::HEADERS);
    }

    private function assertNoContent(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', (string) $this->client->getResponse()->getContent());
    }

    private function onlyReport(): Report
    {
        $reports = self::getContainer()->get(ReportRepository::class)->findAll();
        $this->assertCount(1, $reports);

        return $reports[0];
    }

    /** @param array<string, mixed> $attributes */
    private function onlinePublication(array $attributes): Publication
    {
        return PublicationFactory::new(array_merge([
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => PublicationSubCategoryFactory::new()->asChronique(),
        ], $attributes))->create();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Report;

use App\Entity\Report\Report;
use App\Entity\User;
use App\Enum\Moderation\ModerationActionType;
use App\Enum\Notification\NotificationType;
use App\Enum\Report\ReportOutcome;
use App\Enum\Report\ReportTargetType;
use App\Repository\Moderation\ModerationActionRepository;
use App\Repository\Notification\NotificationRepository;
use App\Repository\Report\ReportRepository;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Report\ReportFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminReportTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_a_member_cannot_open_the_queue(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', '/api/admin/reports');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Access Denied. The user doesn't have ROLE_ADMIN.",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Access Denied. The user doesn't have ROLE_ADMIN.",
        ]);
    }

    public function test_the_queue_lists_pending_first_with_a_count_per_target(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $author = UserFactory::new()->create(['username' => 'spammer', 'email' => 'spammer@test.com']);
        $reporter = UserFactory::new()->create(['username' => 'vigilant', 'email' => 'vigilant@test.com']);
        $resolved = ReportFactory::new([
            'reporter' => $reporter,
            'targetAuthor' => $author,
            'targetId' => 'b6f1a6a0-0000-4000-8000-000000000001',
            'creationDatetime' => new \DateTimeImmutable('2026-10-03 10:00:00'),
            'resolutionDatetime' => new \DateTimeImmutable('2026-10-04 10:00:00'),
            'resolvedBy' => $admin,
            'outcome' => ReportOutcome::Dismissed,
        ])->create();
        $pending = ReportFactory::new([
            'reporter' => $reporter,
            'targetAuthor' => $author,
            'targetId' => (string) $author->id,
            'details' => 'Il spamme',
            'creationDatetime' => new \DateTimeImmutable('2026-10-01 10:00:00'),
        ])->create();
        $older = ReportFactory::new([
            'reporter' => $reporter,
            'targetAuthor' => $author,
            'targetId' => (string) $author->id,
            'creationDatetime' => new \DateTimeImmutable('2026-09-30 10:00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/reports?status=pending');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminReport',
            '@id' => '/api/admin/reports',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->expected($pending, $reporter, $author, 2),
                $this->expected($older, $reporter, $author, 2),
            ],
            'view' => [
                '@id' => '/api/admin/reports?status=pending',
                '@type' => 'PartialCollectionView',
            ],
        ]);
        $this->assertNotNull($resolved->resolutionDatetime);
    }

    public function test_the_detail_compares_the_snapshot_with_the_live_content(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $post = ForumPostFactory::new(['content' => 'Texte modifié depuis'])->create();
        $reporter = UserFactory::new()->create(['username' => 'vigilant', 'email' => 'vigilant@test.com']);
        $report = ReportFactory::new([
            'reporter' => $reporter,
            'targetType' => ReportTargetType::ForumPost,
            'targetId' => (string) $post->id,
            'targetAuthor' => $post->creator,
            'snapshotText' => 'Texte au moment du signalement',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/reports/' . $report->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals(['@context' => '/api/contexts/AdminReport'] + $this->expected($report, $reporter, $post->creator, 1, 'edited'));
    }

    public function test_the_detail_says_when_the_content_is_gone(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $reporter = UserFactory::new()->create(['username' => 'vigilant', 'email' => 'vigilant@test.com']);
        $report = ReportFactory::new([
            'reporter' => $reporter,
            'targetType' => ReportTargetType::ForumPost,
            'targetId' => 'b6f1a6a0-0000-4000-8000-0000000000ff',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/reports/' . $report->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals(['@context' => '/api/contexts/AdminReport'] + $this->expected($report, $reporter, null, 1, 'removed'));
    }

    public function test_dismissing_closes_every_pending_report_on_the_same_content(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $first = ReportFactory::new(['targetId' => 'b6f1a6a0-0000-4000-8000-000000000001'])->create();
        ReportFactory::new(['targetId' => 'b6f1a6a0-0000-4000-8000-000000000001'])->create();
        $other = ReportFactory::new(['targetId' => 'b6f1a6a0-0000-4000-8000-000000000002'])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/reports/' . $first->id . '/dismiss');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', (string) $this->client->getResponse()->getContent());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reports = self::getContainer()->get(ReportRepository::class);
        $this->assertSame(1, $reports->countPending());
        $this->assertTrue($reports->find($other->id)?->isPending());
        $this->assertSame(ReportOutcome::Dismissed, $reports->find($first->id)?->outcome);
        $actions = self::getContainer()->get(ModerationActionRepository::class)->findAll();
        $this->assertCount(1, $actions);
        $this->assertSame(ModerationActionType::ReportDismissed, $actions[0]->type);
    }

    /** DSA art. 16(5) (#1125): each reporter of the content learns the decision, and only them. */
    public function test_dismissing_tells_each_reporter_of_that_content(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $target = ['targetId' => 'b6f1a6a0-0000-4000-8000-000000000001', 'snapshotContext' => ['username' => 'spammer']];
        $firstReporter = UserFactory::new()->create(['username' => 'first_reporter', 'email' => 'first@test.com']);
        $secondReporter = UserFactory::new()->create(['username' => 'second_reporter', 'email' => 'second@test.com']);
        $suspendedReporter = UserFactory::new()->create(['username' => 'suspended_reporter', 'email' => 'suspended@test.com', 'suspensionDatetime' => new \DateTimeImmutable()]);
        $earlierReporter = UserFactory::new()->create(['username' => 'earlier_reporter', 'email' => 'earlier@test.com']);
        $otherReporter = UserFactory::new()->create(['username' => 'other_reporter', 'email' => 'other@test.com']);
        $first = ReportFactory::new(['reporter' => $firstReporter] + $target)->create();
        ReportFactory::new(['reporter' => $secondReporter] + $target)->create();
        ReportFactory::new(['reporter' => $suspendedReporter] + $target)->create();
        // The moderator reported it too: they need no word of their own decision.
        ReportFactory::new(['reporter' => $admin] + $target)->create();
        // Already decided on before: told then, not again.
        ReportFactory::new([
            'reporter' => $earlierReporter,
            'resolutionDatetime' => new \DateTimeImmutable('2026-09-01'),
            'outcome' => ReportOutcome::Dismissed,
        ] + $target)->create();
        ReportFactory::new(['reporter' => $otherReporter, 'targetId' => 'b6f1a6a0-0000-4000-8000-000000000002'])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/reports/' . $first->id . '/dismiss');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $expected = ['target_type' => 'user', 'target_label' => 'spammer', 'outcome' => 'dismissed'];
        $this->assertDecisionNotified($firstReporter, $expected);
        $this->assertDecisionNotified($secondReporter, $expected);
        foreach ([$suspendedReporter, $admin, $earlierReporter, $otherReporter] as $untold) {
            $this->assertSame([], self::getContainer()->get(NotificationRepository::class)->findForRecipient($untold, 10, 0));
        }
    }

    public function test_a_report_already_handled_cannot_be_dismissed_again(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $report = ReportFactory::new([
            'resolutionDatetime' => new \DateTimeImmutable('2026-10-01'),
            'outcome' => ReportOutcome::Dismissed,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/reports/' . $report->id . '/dismiss');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce signalement est déjà traité',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ce signalement est déjà traité',
        ]);
        $this->assertSame(0, self::getContainer()->get(ModerationActionRepository::class)->count());
    }

    public function test_suspending_the_author_closes_the_reports_on_that_content(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $author = UserFactory::new()->create(['username' => 'spammer', 'email' => 'spammer@test.com']);
        $target = ['targetAuthor' => $author, 'targetId' => (string) $author->id, 'snapshotContext' => ['username' => 'spammer']];
        $firstReporter = UserFactory::new()->create(['username' => 'first_reporter', 'email' => 'first@test.com']);
        $secondReporter = UserFactory::new()->create(['username' => 'second_reporter', 'email' => 'second@test.com']);
        $report = ReportFactory::new(['reporter' => $firstReporter] + $target)->create();
        ReportFactory::new(['reporter' => $secondReporter] + $target)->create();
        $suspendedReporter = UserFactory::new()->create(['username' => 'suspended_reporter', 'email' => 'suspended@test.com', 'suspensionDatetime' => new \DateTimeImmutable()]);
        ReportFactory::new(['reporter' => $suspendedReporter] + $target)->create();
        ReportFactory::new(['reporter' => $admin] + $target)->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/reports/' . $report->id . '/suspend-author', ['reason' => 'Spam répété'], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $expected = ['target_type' => 'user', 'target_label' => 'spammer', 'outcome' => 'account_suspended'];
        $this->assertDecisionNotified($firstReporter, $expected);
        $this->assertDecisionNotified($secondReporter, $expected);
        foreach ([$suspendedReporter, $admin, $author] as $untold) {
            $this->assertSame([], self::getContainer()->get(NotificationRepository::class)->findForRecipient($untold, 10, 0));
        }
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $suspended = self::getContainer()->get(UserRepository::class)->find($author->id);
        $this->assertTrue($suspended?->isSuspended());
        $this->assertSame('Spam répété', $suspended?->suspensionReason);
        $reports = self::getContainer()->get(ReportRepository::class);
        $this->assertSame(ReportOutcome::AccountSuspended, $reports->find($report->id)?->outcome);
        $this->assertSame(0, $reports->countPending());
    }

    /** A notification failure never undoes the decision (epic #689 contract item 1). */
    public function test_a_notification_failure_does_not_undo_the_dismissal(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $report = ReportFactory::new()->create();
        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/reports/' . $report->id . '/dismiss');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame(ReportOutcome::Dismissed, self::getContainer()->get(ReportRepository::class)->find($report->id)?->outcome);
    }

    public function test_suspending_needs_a_reason(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $report = ReportFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/reports/' . $report->id . '/suspend-author', ['reason' => ''], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'reason', 'message' => 'Veuillez indiquer la raison de la suspension', 'code' => NotBlank::IS_BLANK_ERROR],
            ],
            'detail' => 'reason: Veuillez indiquer la raison de la suspension',
            'description' => 'reason: Veuillez indiquer la raison de la suspension',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_administrator_cannot_be_suspended(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $otherAdmin = UserFactory::new()->create(['username' => 'other_admin', 'email' => 'other_admin@test.com', 'roles' => ['ROLE_ADMIN']]);
        $report = ReportFactory::new(['targetAuthor' => $otherAdmin, 'targetId' => (string) $otherAdmin->id])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/reports/' . $report->id . '/suspend-author', ['reason' => 'Test'], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Un administrateur ne peut pas être suspendu',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Un administrateur ne peut pas être suspendu',
        ]);
    }

    public function test_an_unknown_report_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::new()->asAdminUser()->create());
        $this->client->request('POST', '/api/admin/reports/not-a-uuid/dismiss');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Signalement introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Signalement introuvable',
        ]);
    }

    public function test_an_admin_lifts_a_suspension_from_the_user_page(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $account = UserFactory::new()->create([
            'username' => 'repenti',
            'email' => 'repenti@test.com',
            'suspensionDatetime' => new \DateTimeImmutable(),
            'suspensionReason' => 'Spam',
        ]);

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/users/' . $account->id . '/lift-suspension');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertFalse(self::getContainer()->get(UserRepository::class)->find($account->id)?->isSuspended());
    }

    public function test_an_admin_suspends_from_the_user_page(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $account = UserFactory::new()->create(['username' => 'fauteur', 'email' => 'fauteur@test.com']);

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/users/' . $account->id . '/suspend', ['reason' => 'Harcèlement'], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame('Harcèlement', self::getContainer()->get(UserRepository::class)->find($account->id)?->suspensionReason);
    }

    /** @return array<string, mixed> */
    private function expected(Report $report, object $reporter, ?object $author, int $pendingCount, ?string $liveState = null): array
    {
        return [
            '@id' => '/api/admin/reports/' . $report->id,
            '@type' => 'AdminReport',
            'id' => (string) $report->id,
            'target_type' => $report->targetType->value,
            'target_id' => $report->targetId,
            'reason' => $report->reason->value,
            'details' => $report->details,
            'snapshot_text' => $report->snapshotText,
            'snapshot_context' => $report->snapshotContext,
            'reporter' => ['id' => $reporter->id, 'username' => $reporter->username],
            'target_author' => $author === null ? null : ['id' => $author->id, 'username' => $author->username, 'is_suspended' => false, 'is_admin' => false],
            'pending_report_count' => $pendingCount,
            'live_state' => $liveState,
            'creation_datetime' => $report->creationDatetime->format(\DateTimeInterface::ATOM),
            'resolution_datetime' => null,
            'resolved_by_username' => null,
            'outcome' => null,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function assertDecisionNotified(User $reporter, array $payload): void
    {
        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($reporter, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::ReportResolved, $notifications[0]->type);
        $this->assertSame($payload, $notifications[0]->payload);
    }

    private function throwingNotificationCreator(): NotificationCreator
    {
        return new readonly class extends NotificationCreator {
            public function __construct()
            {
            }

            public function create(User $recipient, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }

            public function createForRecipients(iterable $recipients, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }
        };
    }
}

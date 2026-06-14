<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Finance;

use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\FinanceEntrySplitRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\FinanceEntrySplitFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\BandSpace\EntryNotPaidValidator;
use App\Validator\BandSpace\SplitNotPersonalValidator;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class FinanceEntrySplitCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_split(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $splitRepository = self::getContainer()->get(FinanceEntrySplitRepository::class);
        $splits = $splitRepository->findByEntry($entry);
        $this->assertCount(1, $splits);

        $split = $splits[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/FinanceEntrySplit',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits/' . $split->id,
            '@type' => 'FinanceEntrySplit',
            'id' => $split->id,
            'band_space_id' => $bandSpace->id,
            'entry_id' => $entry->id,
            'member_id' => (string) $membership->id,
            'member_name' => $user->username,
            'is_former_member' => false,
            'amount' => 25000,
            'creation_datetime' => $split->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Finance, $entry->id);
        $this->assertCount(1, $activities);
        $this->assertSame('split_added', $activities[0]->type);
        $this->assertSame(
            [
                'split_id' => (string) $split->id,
                'member_id' => (string) $membership->id,
                'member_username' => $user->username,
                'amount' => 25000,
            ],
            $activities[0]->payload,
        );
    }

    public function test_create_split_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_split_inactive_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $owner = UserFactory::new()->create(['username' => 'owner_user', 'email' => 'owner@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Left,
        ])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_create_split_exceeds_entry_amount(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $secondUser = UserFactory::new()->create(['username' => 'second_user', 'email' => 'second@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership1 = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $membership2 = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $secondUser])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'scope' => FinanceEntryScope::Band,
            'amount' => 10000,
        ])->create();

        FinanceEntrySplitFactory::new([
            'entry' => $entry,
            'member' => $membership1,
            'amount' => 8000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership2->id, 'amount' => 5000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_create_split_on_paid_entry_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Paid,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . EntryNotPaidValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
                    'code' => EntryNotPaidValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
            'description' => 'Impossible de modifier une entrée payée. Repassez le statut à Engagé.',
            'type' => '/validation_errors/' . EntryNotPaidValidator::ERROR_CODE,
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_split_on_personal_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Studio',
            'position' => 0,
        ])->create();

        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Mon achat perso',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Planned,
            'scope' => FinanceEntryScope::Personal,
            'amount' => 80000,
            'member' => $membership,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . SplitNotPersonalValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'La répartition n\'est pas disponible pour les entrées personnelles',
                    'code' => SplitNotPersonalValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'La répartition n\'est pas disponible pour les entrées personnelles',
            'description' => 'La répartition n\'est pas disponible pour les entrées personnelles',
            'type' => '/validation_errors/' . SplitNotPersonalValidator::ERROR_CODE,
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_split_notifies_the_assigned_member(): void
    {
        $actor = UserFactory::new()->asBaseUser()->create();
        $assignee = UserFactory::new()->create(['username' => 'assignee', 'email' => 'assignee@test.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $actor])->create();
        $assigneeMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $assignee])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($actor);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $assigneeMembership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $notifications = $notificationRepository->findForRecipient($assignee, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceFinanceSplitAssigned, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => (string) $bandSpace->id,
            'band_space_name' => 'The Rockers',
            'entry_id' => (string) $entry->id,
            'entry_label' => 'Recording session',
            'amount' => 25000,
            'actor_id' => (string) $actor->id,
            'actor_username' => $actor->username,
        ], $notifications[0]->payload);

        // The actor (who created the split) receives nothing.
        $this->assertCount(0, $notificationRepository->findForRecipient($actor, 10, 0));
    }

    public function test_create_split_for_self_notifies_no_one(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $membership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($user, 10, 0));
    }

    public function test_create_split_for_a_former_member_notifies_no_one(): void
    {
        $actor = UserFactory::new()->asBaseUser()->create();
        $formerMember = UserFactory::new()->create(['username' => 'former', 'email' => 'former@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $actor])->create();
        $formerMembership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $formerMember,
            'status' => MembershipStatus::Left,
        ])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        $this->client->loginUser($actor);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $formerMembership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        // The split is still created for the former member (historical accounting -
        // the splits endpoint flags it via is_former_member), but the listener must not
        // notify someone who already left the band space.
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertCount(1, self::getContainer()->get(FinanceEntrySplitRepository::class)->findByEntry($entry));
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($formerMember, 10, 0));
    }

    public function test_notification_failure_does_not_break_split_creation(): void
    {
        $actor = UserFactory::new()->asBaseUser()->create();
        $assignee = UserFactory::new()->create(['username' => 'assignee', 'email' => 'assignee@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $actor])->create();
        $assigneeMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $assignee])->create();

        $category = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Studio', 'position' => 0])->create();
        $entry = FinanceEntryFactory::new([
            'category' => $category,
            'label' => 'Recording session',
            'type' => FinanceEntryType::Expense,
            'status' => FinanceEntryStatus::Committed,
            'amount' => 50000,
        ])->create();

        // A notification failure must never roll back or 500 the split (epic #689 contract item 1).
        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($actor);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/finance/entries/' . $entry->id . '/splits',
            ['member_id' => (string) $assigneeMembership->id, 'amount' => 25000],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // The split was still persisted, and no notification was recorded.
        $this->assertCount(1, self::getContainer()->get(FinanceEntrySplitRepository::class)->findByEntry($entry));
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($assignee, 10, 0));
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

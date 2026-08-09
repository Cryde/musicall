<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCommentFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class TaskCommentNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_mentioning_members_notifies_them_not_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author, 'title' => 'Ma tâche'])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $taskId = (string) $task->id;
        $authorId = (string) $author->id;
        $authorUsername = $author->username;
        $content = 'cc @[' . $alice->id . '] et @[' . $bob->id . ']';

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments',
            ['content' => $content],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $comment = self::getContainer()->get(TaskCommentRepository::class)->findByTask($task)[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskComment',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments/' . $comment->id,
            '@type' => 'TaskComment',
            'id' => $comment->id,
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'author_id' => $author->id,
            'author_username' => $authorUsername,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $comment->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $expectedPayload = [
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'task_title' => 'Ma tâche',
            'comment_id' => (string) $comment->id,
            'actor_id' => $authorId,
            'actor_username' => $authorUsername,
        ];
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        foreach ([$alice, $bob] as $recipient) {
            $notifications = $notificationRepository->findForRecipient($recipient, 10, 0);
            $this->assertCount(1, $notifications);
            $this->assertSame(NotificationType::TaskMention, $notifications[0]->type);
            $this->assertSame($expectedPayload, $notifications[0]->payload);
        }

        // The comment author (not among the mentioned users here) receives nothing.
        $this->assertCount(0, $notificationRepository->findForRecipient($author, 10, 0));
    }

    public function test_mentioning_a_non_member_creates_no_notification(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author, 'title' => 'Ma tâche'])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments',
            ['content' => 'Salut @[' . $stranger->id . ']'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($stranger, 10, 0));
    }

    public function test_self_mention_does_not_notify_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author, 'title' => 'Ma tâche'])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments',
            ['content' => 'Note pour moi @[' . $author->id . ']'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0));
    }

    public function test_mentioning_self_alongside_a_member_notifies_only_the_member(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author, 'title' => 'Ma tâche'])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments',
            ['content' => 'cc @[' . $author->id . '] et @[' . $alice->id . ']'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // The author is filtered out of the mention list; only the other mentioned member is notified.
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $this->assertCount(1, $notificationRepository->findForRecipient($alice, 10, 0));
        $this->assertCount(0, $notificationRepository->findForRecipient($author, 10, 0));
    }

    public function test_editing_a_comment_notifies_the_member_it_newly_mentions(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $carol = UserFactory::new()->create(['username' => 'carol', 'email' => 'carol@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $carol])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $author,
            'assignees' => new ArrayCollection([$carol]),
            'title' => 'Ma tâche',
        ])->create();
        $comment = TaskCommentFactory::new([
            'task' => $task,
            'author' => $author,
            'content' => 'Premier jet',
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $taskId = (string) $task->id;
        $commentId = (string) $comment->id;
        $content = 'Premier jet, @[' . $alice->id . '] ton avis ?';

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments/' . $commentId,
            ['content' => $content],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();

        $refreshed = self::getContainer()->get(TaskCommentRepository::class)->find($commentId);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskComment',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments/' . $commentId,
            '@type' => 'TaskComment',
            'id' => $commentId,
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $aliceNotifications = $notificationRepository->findForRecipient($alice, 10, 0);
        $this->assertCount(1, $aliceNotifications);
        $this->assertSame(NotificationType::TaskMention, $aliceNotifications[0]->type);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'task_title' => 'Ma tâche',
            'comment_id' => $commentId,
            'actor_id' => (string) $author->id,
            'actor_username' => $author->username,
        ], $aliceNotifications[0]->payload);

        // An edit is not a new comment: the assignee who was not named hears nothing, and neither
        // does the author.
        $this->assertCount(0, $notificationRepository->findForRecipient($carol, 10, 0));
        $this->assertCount(0, $notificationRepository->findForRecipient($author, 10, 0));
        $this->assertCount(1, $notificationRepository->findAll());
    }

    public function test_editing_a_comment_does_not_notify_a_member_it_already_mentioned(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $author,
            'title' => 'Ma tâche',
        ])->create();
        $comment = TaskCommentFactory::new([
            'task' => $task,
            'author' => $author,
            'content' => 'Salut @[' . $alice->id . ']',
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            ['content' => 'Salut @[' . $alice->id . '] et @[' . $bob->id . ']'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseIsSuccessful();

        // Alice was named by the text the comment already held, so she was told when it was written.
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $bobNotifications = $notificationRepository->findForRecipient($bob, 10, 0);
        $this->assertCount(1, $bobNotifications);
        $this->assertSame(NotificationType::TaskMention, $bobNotifications[0]->type);
        $this->assertSame([
            'band_space_id' => (string) $bandSpace->id,
            'task_id' => (string) $task->id,
            'task_title' => 'Ma tâche',
            'comment_id' => (string) $comment->id,
            'actor_id' => (string) $author->id,
            'actor_username' => $author->username,
        ], $bobNotifications[0]->payload);

        $this->assertCount(0, $notificationRepository->findForRecipient($alice, 10, 0));
        $this->assertCount(1, $notificationRepository->findAll());
    }

    public function test_comment_notifies_task_participants_not_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $carol = UserFactory::new()->create(['username' => 'carol', 'email' => 'carol@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $carol])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $author,
            'assignees' => new ArrayCollection([$bob]),
            'title' => 'Ma tâche',
        ])->create();
        // Carol becomes a participant by having already commented (seeded in the past so the author's
        // new comment sorts last; the seeded comment itself triggers no notification).
        TaskCommentFactory::new([
            'task' => $task,
            'author' => $carol,
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $taskId = (string) $task->id;
        $content = 'On avance bien sur cette tâche';

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments',
            ['content' => $content],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $comments = self::getContainer()->get(TaskCommentRepository::class)->findByTask($task);
        $authorComment = end($comments);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskComment',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments/' . $authorComment->id,
            '@type' => 'TaskComment',
            'id' => $authorComment->id,
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'author_id' => $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $authorComment->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $expectedPayload = [
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'task_title' => 'Ma tâche',
            'comment_id' => (string) $authorComment->id,
            'actor_id' => (string) $author->id,
            'actor_username' => $author->username,
        ];
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        foreach ([$bob, $carol] as $recipient) {
            $notifications = $notificationRepository->findForRecipient($recipient, 10, 0);
            $this->assertCount(1, $notifications);
            $this->assertSame(NotificationType::TaskComment, $notifications[0]->type);
            $this->assertSame($expectedPayload, $notifications[0]->payload);
        }

        // The comment author receives nothing.
        $this->assertCount(0, $notificationRepository->findForRecipient($author, 10, 0));
    }

    public function test_mentioned_participant_is_not_notified_twice(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $carol = UserFactory::new()->create(['username' => 'carol', 'email' => 'carol@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $carol])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $author,
            'assignees' => new ArrayCollection([$bob, $carol]),
            'title' => 'Ma tâche',
        ])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments',
            ['content' => 'Hey @[' . $bob->id . ']'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $comment = self::getContainer()->get(TaskCommentRepository::class)->findByTask($task)[0];
        // Both notifications carry the same payload; only the type tells them apart.
        $expectedPayload = [
            'band_space_id' => (string) $bandSpace->id,
            'task_id' => (string) $task->id,
            'task_title' => 'Ma tâche',
            'comment_id' => (string) $comment->id,
            'actor_id' => (string) $author->id,
            'actor_username' => $author->username,
        ];
        $notificationRepository = self::getContainer()->get(NotificationRepository::class);

        // Bob was @-mentioned: exactly one notification, the richer mention - not also a comment one.
        $bobNotifications = $notificationRepository->findForRecipient($bob, 10, 0);
        $this->assertCount(1, $bobNotifications);
        $this->assertSame(NotificationType::TaskMention, $bobNotifications[0]->type);
        $this->assertSame($expectedPayload, $bobNotifications[0]->payload);

        // Carol is an assignee but not mentioned: she gets the participant comment notification.
        $carolNotifications = $notificationRepository->findForRecipient($carol, 10, 0);
        $this->assertCount(1, $carolNotifications);
        $this->assertSame(NotificationType::TaskComment, $carolNotifications[0]->type);
        $this->assertSame($expectedPayload, $carolNotifications[0]->payload);

        // The author gets nothing.
        $this->assertCount(0, $notificationRepository->findForRecipient($author, 10, 0));
    }

    public function test_comment_does_not_notify_a_participant_who_left_the_band_space(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $gone = UserFactory::new()->create(['username' => 'gone', 'email' => 'gone@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $gone,
            'status' => MembershipStatus::Kicked,
            'leftDatetime' => new \DateTime('2026-01-02 10:00:00'),
        ])->create();
        // The departed member is a participant three times over: they created the task, they were
        // assigned to it and they commented on it. Departure clears the assignment going forward,
        // but an assignment predating the fix, the authorship of the task and the comment they wrote
        // all still name them, so the fan-out has to be the one refusing to notify them.
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $gone,
            'assignees' => new ArrayCollection([$gone, $bob]),
            'title' => 'Ma tâche',
        ])->create();
        TaskCommentFactory::new([
            'task' => $task,
            'author' => $gone,
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();

        $bandSpaceId = (string) $bandSpace->id;
        $taskId = (string) $task->id;
        $content = 'On reprend cette tâche';

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments',
            ['content' => $content],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $comments = self::getContainer()->get(TaskCommentRepository::class)->findByTask($task);
        $authorComment = end($comments);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskComment',
            '@id' => '/api/band_spaces/' . $bandSpaceId . '/tasks/' . $taskId . '/comments/' . $authorComment->id,
            '@type' => 'TaskComment',
            'id' => $authorComment->id,
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'author_id' => $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $authorComment->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);

        // The member who is still in the band keeps hearing about the task they are on.
        $bobNotifications = $notificationRepository->findForRecipient($bob, 10, 0);
        $this->assertCount(1, $bobNotifications);
        $this->assertSame(NotificationType::TaskComment, $bobNotifications[0]->type);
        $this->assertSame([
            'band_space_id' => $bandSpaceId,
            'task_id' => $taskId,
            'task_title' => 'Ma tâche',
            'comment_id' => (string) $authorComment->id,
            'actor_id' => (string) $author->id,
            'actor_username' => $author->username,
        ], $bobNotifications[0]->payload);

        // The one who is out hears nothing, and nobody else was notified either.
        $this->assertCount(0, $notificationRepository->findForRecipient($gone, 10, 0));
        $this->assertCount(0, $notificationRepository->findForRecipient($author, 10, 0));
        $this->assertCount(1, $notificationRepository->findAll());
    }

    public function test_comment_with_no_other_participant_creates_no_notification(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author, 'title' => 'Ma tâche'])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments',
            ['content' => 'On avance bien !'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // The author is the task's only participant, and is excluded as the comment actor.
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    public function test_notification_failure_does_not_break_the_comment(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author, 'title' => 'Ma tâche'])->create();

        // A notification failure must never roll back or 500 the comment (epic #689 contract item 1).
        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments',
            ['content' => 'Hey @[' . $alice->id . ']'],
            self::HEADERS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertCount(1, self::getContainer()->get(TaskCommentRepository::class)->findByTask($task));
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($alice, 10, 0));
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

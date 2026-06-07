<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
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

    public function test_comment_without_mentions_creates_no_notification(): void
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

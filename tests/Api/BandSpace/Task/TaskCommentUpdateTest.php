<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\Role;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCommentFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskCommentUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_author_can_edit_own_comment(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $comment = TaskCommentFactory::new([
            'task' => $task,
            'author' => $user,
            'content' => 'Premier jet',
        ])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            ['content' => 'Version corrigée'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $refreshed = $commentRepo->find($comment->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskComment',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            '@type' => 'TaskComment',
            'id' => (string) $comment->id,
            'task_id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'author_id' => (string) $user->id,
            'author_username' => $user->username,
            'author_profile_picture_url' => null,
            'content' => 'Version corrigée',
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);
        $this->assertSame('Version corrigée', $refreshed->content);
        $this->assertNotNull($refreshed->updateDatetime);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(1, $activities);
        $this->assertSame('comment_edited', $activities[0]->type);
        $this->assertSame(['comment_id' => (string) $comment->id], $activities[0]->payload);
    }

    public function test_editing_a_comment_records_the_mentions_it_adds(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author])->create();
        $comment = TaskCommentFactory::new([
            'task' => $task,
            'author' => $author,
            'content' => 'Premier jet',
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();

        // The editor highlights the name as soon as it is typed, so the author leaves believing the
        // ping went out. It has to actually go out.
        $this->client->loginUser($author);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            ['content' => 'Premier jet, @[' . $alice->id . '] ton avis ?'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $refreshed = $commentRepo->find($comment->id);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskComment',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            '@type' => 'TaskComment',
            'id' => (string) $comment->id,
            'task_id' => (string) $task->id,
            'band_space_id' => (string) $bandSpace->id,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => 'Premier jet, @[' . $alice->id . '] ton avis ?',
            'creation_datetime' => $refreshed->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $refreshed->updateDatetime->format(\DateTimeInterface::ATOM),
        ]);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(2, $activities);

        $mentions = array_values(array_filter($activities, fn($activity): bool => $activity->type === 'mention'));
        $this->assertCount(1, $mentions);
        $this->assertSame([
            'mentioned_user_id' => (string) $alice->id,
            'mentioned_username' => 'alice',
        ], $mentions[0]->payload);
    }

    public function test_editing_a_comment_leaves_a_mention_it_already_carried_alone(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $alice = UserFactory::new()->create(['username' => 'alice', 'email' => 'alice@test.com']);
        $bob = UserFactory::new()->create(['username' => 'bob', 'email' => 'bob@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $alice])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $bob])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author])->create();
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

        // Alice was named by the text the comment already held: she heard about it then, and a typo
        // fixed three edits later must not put her through it again.
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $mentions = array_values(array_filter($activities, fn($activity): bool => $activity->type === 'mention'));
        $this->assertCount(1, $mentions);
        $this->assertSame([
            'mentioned_user_id' => (string) $bob->id,
            'mentioned_username' => 'bob',
        ], $mentions[0]->payload);
    }

    public function test_admin_cannot_edit_other_users_comment(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $admin = UserFactory::new()->create(['username' => 'admin_user', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author])->create();
        $comment = TaskCommentFactory::new(['task' => $task, 'author' => $author, 'content' => 'Original'])->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            ['content' => 'Réécrit par admin'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $this->assertSame('Original', $commentRepo->find($comment->id)->content);
    }

    public function test_other_member_cannot_edit_comment(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author])->create();
        $comment = TaskCommentFactory::new(['task' => $task, 'author' => $author])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            ['content' => 'Bidouille'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_non_member_cannot_edit_comment(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author])->create();
        $comment = TaskCommentFactory::new(['task' => $task, 'author' => $author])->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            ['content' => 'Hack'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_empty_content_is_rejected(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $comment = TaskCommentFactory::new(['task' => $task, 'author' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            ['content' => ''],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

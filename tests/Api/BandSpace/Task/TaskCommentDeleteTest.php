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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaskCommentDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_author_can_delete_own_comment(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $comment = TaskCommentFactory::new(['task' => $task, 'author' => $user])->create();
        $commentId = (string) $comment->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $commentId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $this->assertNull($commentRepo->find($commentId));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(1, $activities);
        $this->assertSame('comment_deleted', $activities[0]->type);
        $this->assertSame(['comment_id' => $commentId], $activities[0]->payload);
    }

    public function test_admin_can_delete_other_users_comment(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $admin = UserFactory::new()->create(['username' => 'admin_user', 'email' => 'admin@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author])->create();
        $comment = TaskCommentFactory::new(['task' => $task, 'author' => $author])->create();
        $commentId = (string) $comment->id;

        $this->client->loginUser($admin);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $commentId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $this->assertNull($commentRepo->find($commentId));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(1, $activities);
        $this->assertSame('comment_deleted', $activities[0]->type);
    }

    public function test_other_member_cannot_delete_comment(): void
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
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $this->assertNotNull($commentRepo->find($comment->id));
    }

    public function test_non_member_cannot_delete_comment(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $author])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $author])->create();
        $comment = TaskCommentFactory::new(['task' => $task, 'author' => $author])->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id . '/comments/' . $comment->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

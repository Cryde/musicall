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

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
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
        $this->assertJsonContains([
            '@type' => 'TaskComment',
            'id' => $comment->id,
            'content' => 'Version corrigée',
        ]);

        $commentRepo = self::getContainer()->get(TaskCommentRepository::class);
        $refreshed = $commentRepo->find($comment->id);
        $this->assertSame('Version corrigée', $refreshed->content);
        $this->assertNotNull($refreshed->updateDatetime);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Task, $task->id);
        $this->assertCount(1, $activities);
        $this->assertSame('comment_edited', $activities[0]->type);
        $this->assertSame(['comment_id' => (string) $comment->id], $activities[0]->payload);
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

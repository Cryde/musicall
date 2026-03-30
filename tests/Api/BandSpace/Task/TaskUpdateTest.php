<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\TaskActivityRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaskUpdateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_update_task_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'status' => TaskStatus::Todo,
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id,
            ['status' => 'in_progress'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['status' => 'in_progress']);

        $activityRepo = self::getContainer()->get(TaskActivityRepository::class);
        $activities = $activityRepo->findByTask($task->_real());
        $this->assertCount(1, $activities);
        $this->assertSame('status_changed', $activities[0]->type);
        $this->assertSame(['from' => 'todo', 'to' => 'in_progress'], $activities[0]->payload);
    }

    public function test_update_task_partial(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $task = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Original title',
            'status' => TaskStatus::Todo,
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id,
            ['title' => 'Updated title'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'title' => 'Updated title',
            'status' => 'todo',
        ]);
    }

    public function test_update_task_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $owner])->create();

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/tasks/' . $task->_real()->id,
            ['title' => 'Hacked'],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

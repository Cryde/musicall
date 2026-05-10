<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Repository\BandSpace\Filter\TaskFilter;
use App\Repository\BandSpace\TaskRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class TaskCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_task_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            ['title' => 'Acheter des cordes'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(TaskRepository::class);
        $tasks = $repo->findByBandSpace($bandSpace, new TaskFilter());
        $this->assertCount(1, $tasks);

        $task = $tasks[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => $task->id,
            'band_space_id' => $bandSpace->id,
            'title' => 'Acheter des cordes',
            'description' => null,
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => $user->id,
            'created_by_username' => $user->username,
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $task->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'comment_count' => 0,
            'file_count' => 0,
        ]);
    }

    public function test_create_task_with_all_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $assignee = UserFactory::new()->create(['username' => 'assignee_user', 'email' => 'assignee@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $assignee])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Logistique'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            [
                'title' => 'Réserver la salle',
                'description' => 'Appeler la salle pour réserver',
                'status' => 'in_progress',
                'priority' => 'high',
                'due_date' => '2026-04-15',
                'category_id' => $category->id,
                'assignee_ids' => [$assignee->id],
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            'title' => 'Réserver la salle',
            'description' => 'Appeler la salle pour réserver',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => '2026-04-15',
            'category_id' => $category->id,
            'category_name' => 'Logistique',
            'assignees' => [
                [
                    'id' => $assignee->id,
                    'username' => 'assignee_user',
                    'profile_picture_url' => null,
                ],
            ],
        ]);
    }

    public function test_create_task_validation_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            ['title' => ''],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_task_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/tasks',
            ['title' => 'Forbidden'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Repository\BandSpace\TaskCategoryRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskCategoryDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_delete_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/task-categories/' . $category->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_category_detaches_linked_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'category' => $category, 'createdBy' => $user])->create();

        $categoryId = (string) $category->id;
        $taskId = (string) $task->id;

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/task-categories/' . $categoryId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(self::getContainer()->get(TaskCategoryRepository::class)->find($categoryId));
        $this->assertNull(self::getContainer()->get(TaskRepository::class)->find($taskId)->category);
    }

    public function test_delete_category_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/task-categories/' . $category->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

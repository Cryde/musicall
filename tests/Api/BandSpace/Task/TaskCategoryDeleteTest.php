<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaskCategoryDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/task-categories/' . $category->_real()->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_category_with_linked_tasks(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();
        TaskFactory::new(['bandSpace' => $bandSpace, 'category' => $category, 'createdBy' => $user])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/task-categories/' . $category->_real()->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => '1 tâche(s) utilise(nt) encore cette catégorie',
            'status' => 422,
            'type' => '/errors/422',
            'description' => '1 tâche(s) utilise(nt) encore cette catégorie',
        ]);
    }

    public function test_delete_category_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $category = TaskCategoryFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->_real()->id . '/task-categories/' . $category->_real()->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

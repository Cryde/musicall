<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Task;

use App\Repository\BandSpace\TaskCategoryRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TaskCategoryCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/task-categories',
            ['name' => 'Répétitions'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(TaskCategoryRepository::class);
        $categories = $repo->findByBandSpace($bandSpace);
        $this->assertCount(1, $categories);

        $category = $categories[0];
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/task-categories/' . $category->id,
            '@type' => 'TaskCategory',
            'id' => $category->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Répétitions',
            'color' => '#FF6B6B',
            'creation_datetime' => $category->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_second_category_gets_different_color(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        TaskCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'First', 'color' => '#FF6B6B'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/task-categories',
            ['name' => 'Second'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(TaskCategoryRepository::class);
        $created = $repo->findOneBy(['bandSpace' => $bandSpace, 'name' => 'Second']);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaskCategory',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/task-categories/' . $created->id,
            '@type' => 'TaskCategory',
            'id' => $created->id,
            'band_space_id' => $bandSpace->id,
            'name' => 'Second',
            'color' => '#4ECDC4',
            'creation_datetime' => $created->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
    }

    public function test_create_category_validation_empty_name(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/task-categories',
            ['name' => ''],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_category_not_member(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/task-categories',
            ['name' => 'Forbidden'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

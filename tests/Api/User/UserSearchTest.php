<?php

namespace Api\User;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserSearchTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_users_search(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'test1', 'email' => 'base_user1@email.com'])->_real();
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'test2', 'email' => 'base_user2@email.com'])->_real();
        UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'base_user3@email.com'])->_real();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/users/search?search=test');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSearch',
            '@id' => '/api/users/search',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/user_searches/' . $user1->getId(),
                    '@type' => 'UserSearch',
                    'id' =>$user1->getId(),
                    'username' => 'test1',
                ],
                [
                    '@id' => '/api/user_searches/' .$user2->getId(),
                    '@type' => 'UserSearch',
                    'id' => $user2->getId(),
                    'username' => 'test2',
                ],
            ],
            'view' => [
                '@id' => '/api/users/search?search=test',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/users/search{?}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => []
            ],
        ]);
    }


    public function test_users_search_with_invalid_search(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->_real();
        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/users/search?search=te');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'search',
                    'message' => 'Cette chaîne est trop courte. Elle doit avoir au minimum 3 caractères.',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ]
            ],
            'detail' => 'search: Cette chaîne est trop courte. Elle doit avoir au minimum 3 caractères.',
            'description' => 'search: Cette chaîne est trop courte. Elle doit avoir au minimum 3 caractères.',
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
        ]);
    }

    public function test_search_not_logged(): void
    {
        $this->client->request('GET', '/api/users/search?search=test');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }
}
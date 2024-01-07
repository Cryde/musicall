<?php

namespace App\Tests\Api\User;

use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RegisterTest extends ApiTestCase
{
    // todo : test that email is sent !
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_register()
    {
        $userRepository = static::getContainer()->get(UserRepository::class);

        // pre-test: we don't have user in the db
        $this->assertCount(0, $userRepository->findAll());

        $this->client->jsonRequest('POST', '/api/register', [
            'username' => 'super_username',
            'email'    => 'super_email@mail.com',
            'password' => 'password',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            'data' => ['success' => 1],
        ]);

        $results = $userRepository->findAll();
        $this->assertCount(1, $results);
        $this->assertSame('super_username', $results[0]->getUsername());
        $this->assertSame('super_email@mail.com', $results[0]->getEmail());
        $this->assertNotSame('password', $results[0]->getPassword()); // we assert that we don't record plain text password in db
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();

        $this->assertEmailTextBodyContains($email, 'Confirmer votre email');
    }

    public function test_register_with_errors()
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->object();

        $this->client->jsonRequest('POST', '/api/register', [
            'username' => $user1->getUsername(),
            'email'    => $user1->getEmail(),
            'password' => 'pa',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            [
                'propertyPath' => 'username',
                'message'      => 'Ce login est déjà pris',
                'code'         => '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
            ],
            [
                'propertyPath' => 'email',
                'message'      => 'Cet email est déjà utilisé',
                'code'         => '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
            ],
            [
                'propertyPath' => 'plain_password',
                'message' => 'Le mot de passe doit au moins contenir 3 caractères',
                'code'         => '9ff3fdc4-b214-49db-8718-39c315e33d45',
            ],
        ]);
        $this->assertEmailCount(0);
    }

    public function test_register_already_have_account()
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->object();

        $this->client->loginUser($user1);
        $this->client->request('POST', '/api/register');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            'errors' => 'you already have an account',
        ]);
        $this->assertEmailCount(0);
    }
}
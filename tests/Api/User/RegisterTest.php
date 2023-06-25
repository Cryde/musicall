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
            'type'       => 'https://symfony.com/errors/validation',
            'title'      => 'Validation Failed',
            'detail'     => 'username: Ce login est déjà pris
email: Cet email est déjà utilisé
plain_password: Le mot de passe doit au moins contenir 3 caractères',
            'violations' => [
                [
                    'propertyPath' => 'username',
                    'title'        => 'Ce login est déjà pris',
                    'parameters'   => ['{{ value }}' => '"base_admin"'],
                    'type'         => 'urn:uuid:23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
                    'template' => 'Ce login est déjà pris',
                ],
                [
                    'propertyPath' => 'email',
                    'title'        => 'Cet email est déjà utilisé',
                    'parameters'   => ['{{ value }}' => '"base_user@email.com"'],
                    'type'         => 'urn:uuid:23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
                    'template' => 'Cet email est déjà utilisé',
                ],
                [
                    'propertyPath' => 'plain_password',
                    'title'        => 'Le mot de passe doit au moins contenir 3 caractères',
                    'parameters'   => [
                        '{{ value }}' => '"pa"',
                        '{{ limit }}' => '3',
                        '{{ value_length }}' => '2'
                    ],
                    'type'         => 'urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45',
                    'template' => 'Le mot de passe doit au moins contenir 3 caractères',
                ],
            ],
        ]);
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
    }
}
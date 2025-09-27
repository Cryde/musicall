<?php

namespace App\Tests\Api\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserRegisterTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_register(): void
    {
        $userRepository = static::getContainer()->get(UserRepository::class);

        // pre-test: we don't have user in the db
        $this->assertCount(0, $userRepository->findAll());

        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'super_username',
            'password' => 'password',
            'email' => 'super_email@mail.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $results = $userRepository->findAll();
        $this->assertCount(1, $results);
        $this->assertSame('super_username', $results[0]->getUsername());
        $this->assertSame('super_email@mail.com', $results[0]->getEmail());
        $this->assertNotSame('password', $results[0]->getPassword()); // we assert that we don't record plain text password in db

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailTextBodyContains($email, 'Confirmer votre email');
        $this->assertEmailHeaderSame($email, 'templateId', '1');
        $this->assertEmailAddressContains($email, 'From', 'no-reply@musicall.com');
        $this->assertEmailAddressContains($email, 'To', 'super_email@mail.com');
    }


    public function test_register_with_bad_input_1(): void
    {
        // here we check common error in all 3 inputs
        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'a',
            'password' => 'pass',
            'email' => 'email',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=9ff3fdc4-b214-49db-8718-39c315e33d45;1=bd79c0ab-ddba-46cc-a703-a7a4b08de310;2=9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'username: Le nom d\'utilisateur doit au moins contenir 3 caractères
email: Email invalide
password: Le mot de passe doit au moins contenir 6 caractères',
            'status' => 422,
            'type' => '/validation_errors/0=9ff3fdc4-b214-49db-8718-39c315e33d45;1=bd79c0ab-ddba-46cc-a703-a7a4b08de310;2=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'description' => 'username: Le nom d\'utilisateur doit au moins contenir 3 caractères
email: Email invalide
password: Le mot de passe doit au moins contenir 6 caractères',
            'violations' => [
                [
                              'propertyPath' => 'username',
            'message' => 'Le nom d\'utilisateur doit au moins contenir 3 caractères',
            'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
                [
                         'propertyPath' => 'email',
            'message' => 'Email invalide',
            'code' => 'bd79c0ab-ddba-46cc-a703-a7a4b08de310',
                ],
                [
                               'propertyPath' => 'password',
            'message' => 'Le mot de passe doit au moins contenir 6 caractères',
            'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ]
        ]);
        $this->assertEmailCount(0);
    }

    public function test_register_with_bad_input_2(): void
    {
        // here we check bad username format
        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'Lors Username',
            'password' => 'password',
            'email' => 'email@gmail.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'username: Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
            'status' => 422,
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'description' => 'username: Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
            'violations' => [
                [
                    'propertyPath' => 'username',
                    'message' => 'Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ]
        ]);
        $this->assertEmailCount(0);
    }

    public function test_register_with_bad_input_3(): void
    {
        // here we test a username already taken !
        /** @var User $user */
        UserFactory::new()->asBaseUser()->create(['username' => 'username_taken', 'email' => 'base_user1@email.com']);

        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'username_taken',
            'password' => 'password',
            'email' => 'email@gmail.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'username: Ce nom d\'utilisateur est déjà pris',
            'status' => 422,
            'type' => '/validation_errors/23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
            'description' => 'username: Ce nom d\'utilisateur est déjà pris',
            'violations' => [
                [
                    'propertyPath' => 'username',
                    'message' => 'Ce nom d\'utilisateur est déjà pris',
                    'code' => '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
                ],
            ]
        ]);
        $this->assertEmailCount(0);
    }

    public function test_register_with_bad_input_4(): void
    {
        // here we test a email already taken !
        /** @var User $user */
        UserFactory::new()->asBaseUser()->create(['username' => 'username', 'email' => 'email_taken@email.com']);

        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'username_2',
            'password' => 'password',
            'email' => 'email_taken@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'email: Cet email est déjà utilisé',
            'status' => 422,
            'type' => '/validation_errors/23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
            'description' => 'email: Cet email est déjà utilisé',
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'Cet email est déjà utilisé',
                    'code' => '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f',
                ],
            ]
        ]);
        $this->assertEmailCount(0);
    }

    public function test_register_with_logged_user(): void
    {
        /** @var User $user */
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'abc',
            'password' => 'password',
            'email' => 'email@gmail.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous êtes déjà connecté',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'Vous êtes déjà connecté',
        ]);

        $this->assertEmailCount(0);
    }
}

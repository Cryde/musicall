<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\RefreshToken;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserChangeUsernameTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array SERVER_PARAMS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_change_username(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $oldUsername = $user->username;

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Verify username was changed in database
        $this->getEntityManager()->clear();
        $updatedUser = static::getContainer()->get(UserRepository::class)->find($user->id);
        $this->assertSame('new_username', $updatedUser->username);
        $this->assertNotSame($oldUsername, $updatedUser->username);
        $this->assertNotNull($updatedUser->usernameChangedDatetime);
    }

    /**
     * The member's other sessions follow the rename (#1025): the phone and the second browser hold
     * refresh tokens stored under the old username, which nothing resolves any more after it.
     */
    public function test_every_session_follows_the_rename(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'old_name', 'email' => 'old.name@example.com']);
        $someoneElse = UserFactory::new()->asBaseUser()->create(['username' => 'someone_else', 'email' => 'someone@example.com']);
        $this->seedRefreshToken('laptop-token', $user);
        $this->seedRefreshToken('phone-token', $user);
        $this->seedRefreshToken('other-member-token', $someoneElse);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_name'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('new_name', $this->findRefreshToken('laptop-token')?->getUsername());
        $this->assertSame('new_name', $this->findRefreshToken('phone-token')?->getUsername());
        $this->assertSame('someone_else', $this->findRefreshToken('other-member-token')?->getUsername());
        $this->assertSame(0, $this->getEntityManager()->getRepository(RefreshToken::class)->count(['username' => 'old_name']));
        // The browser keeps its refresh token, now under the new name: only the JWT is reissued.
        $cookieNames = array_map(static fn ($cookie): string => $cookie->getName(), $this->client->getResponse()->headers->getCookies());
        sort($cookieNames);
        $this->assertSame(['jwt_hp', 'jwt_s'], $cookieNames);
    }

    /** A device that sends no cookie, the app, still refreshes after a rename made elsewhere. */
    public function test_another_device_still_refreshes_after_the_rename(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'old_name', 'email' => 'old.name@example.com']);
        $this->seedRefreshToken('phone-token', $user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_name'], self::SERVER_PARAMS);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // The phone, unauthenticated, with only its refresh token.
        $this->client->getCookieJar()->clear();
        $this->client->jsonRequest('POST', '/api/native/token/refresh', ['refresh_token' => 'phone-token']);

        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $keys = array_keys($body);
        sort($keys);
        $this->assertSame(['mercure_authorization', 'refresh_token', 'token'], $keys);
        $this->assertSame('new_name', JwtPayload::of($body['token'])['username']);
    }

    public function test_change_username_not_logged(): void
    {
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_change_username_already_taken(): void
    {
        UserFactory::new()->create(['username' => 'taken_username']);
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'taken_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce nom d\'utilisateur est déjà pris.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Ce nom d\'utilisateur est déjà pris.',
        ]);
    }

    public function test_change_username_same_as_current(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => $user->username], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Le nouveau nom d\'utilisateur doit être différent de l\'actuel.',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Le nouveau nom d\'utilisateur doit être différent de l\'actuel.',
        ]);
    }

    public function test_change_username_too_short(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'ab'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
            'detail' => 'new_username: Le nom d\'utilisateur doit au moins contenir 3 caractères',
            'status' => 422,
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'description' => 'new_username: Le nom d\'utilisateur doit au moins contenir 3 caractères',
            'violations' => [
                [
                    'propertyPath' => 'new_username',
                    'message' => 'Le nom d\'utilisateur doit au moins contenir 3 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);
    }

    public function test_change_username_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => str_repeat('a', 41)], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'detail' => 'new_username: Le nom d\'utilisateur doit contenir maximum 40 caractères',
            'status' => 422,
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'description' => 'new_username: Le nom d\'utilisateur doit contenir maximum 40 caractères',
            'violations' => [
                [
                    'propertyPath' => 'new_username',
                    'message' => 'Le nom d\'utilisateur doit contenir maximum 40 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
        ]);
    }

    public function test_change_username_invalid_characters(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'invalid@username!'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'detail' => 'new_username: Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
            'status' => 422,
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'description' => 'new_username: Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
            'violations' => [
                [
                    'propertyPath' => 'new_username',
                    'message' => 'Nom d\'utilisateur invalide : seuls les lettres, chiffres, points et underscores sont autorisés.',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
        ]);
    }

    public function test_change_username_throttled(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'usernameChangedDatetime' => new \DateTimeImmutable('-10 days'),
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_a1b2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d',
            'title' => 'An error occurred',
            'detail' => 'Vous devez attendre 30 jours entre chaque changement de nom d\'utilisateur.',
            'status' => 422,
            'type' => '/validation_errors/music_all_a1b2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d',
            'description' => 'Vous devez attendre 30 jours entre chaque changement de nom d\'utilisateur.',
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'Vous devez attendre 30 jours entre chaque changement de nom d\'utilisateur.',
                    'code' => 'music_all_a1b2c3d4-5e6f-7a8b-9c0d-1e2f3a4b5c6d',
                ],
            ],
        ]);
    }

    public function test_change_username_after_cooldown(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'usernameChangedDatetime' => new \DateTimeImmutable('-31 days'),
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'new_username'], self::SERVER_PARAMS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function seedRefreshToken(string $value, object $user): void
    {
        $this->getEntityManager()->persist(RefreshToken::createForUserWithTtl($value, $user, 3600));
        $this->getEntityManager()->flush();
    }

    private function findRefreshToken(string $value): ?RefreshToken
    {
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $value]);
    }
}

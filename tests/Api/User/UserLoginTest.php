<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\SessionCookies;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserLoginTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_login(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertResponseHasCookie('jwt_hp');
        $this->assertResponseHasCookie('jwt_s');
        $this->assertResponseHasCookie('refresh_token');
        // The reference set: OAuthWebSignInTest and UserChangeUsernameTest expect the same (#1027).
        $this->assertSame([
            'jwt_hp' => ['expires' => 'now + token_ttl', 'path' => '/', 'domain' => null, 'secure' => true, 'httpOnly' => false, 'sameSite' => 'strict', 'partitioned' => false],
            'jwt_s' => ['expires' => 'session', 'path' => '/', 'domain' => null, 'secure' => true, 'httpOnly' => true, 'sameSite' => 'strict', 'partitioned' => false],
            'refresh_token' => ['expires' => 'now + refresh_ttl', 'path' => '/', 'domain' => null, 'secure' => true, 'httpOnly' => true, 'sameSite' => 'lax', 'partitioned' => false],
        ], SessionCookies::describe($this->client->getResponse()->headers->getCookies(), self::getContainer()));
    }

    /** A real login still advances it, unlike a token refresh (#1024, NativeTokenRefreshTest). */
    public function test_a_login_stamps_the_last_login(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['lastLoginDatetime' => new \DateTime('2026-08-01 10:00:00')]);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $this->assertGreaterThan(new \DateTime('-1 minute'), $entityManager->find(User::class, $user1->id)->lastLoginDatetime);
    }

    public function test_login_unverified_user_with_bad_password_returns_generic_error(): void
    {
        // Anti-enumeration: a wrong password must look identical whether or not the
        // account exists / is verified. account_not_verified is only revealed AFTER
        // a correct password (see the test below), so a wrong-password probe cannot
        // detect an unverified account nor resolve its email.
        $user1 = UserFactory::new()->asBaseUser()->create(['confirmationDatetime' => null]);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    public function test_login_unverified_user_with_correct_password_returns_account_not_verified(): void
    {
        // Only the account owner (correct password) learns the unverified status and
        // the email, so the frontend can route them to the verification flow.
        $user1 = UserFactory::new()->asBaseUser()->create(['confirmationDatetime' => null]);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'account_not_verified',
            'email' => $user1->email,
        ]);
    }

    public function test_login_of_a_suspended_account_is_refused(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['suspensionDatetime' => new \DateTimeImmutable('2026-09-01')]);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'password',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertResponseNotHasCookie('refresh_token');
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'account_suspended',
        ]);
    }

    /** A suspended account with a wrong password learns nothing more than anyone else. */
    public function test_login_of_a_suspended_account_with_a_bad_password_is_the_generic_error(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['suspensionDatetime' => new \DateTimeImmutable('2026-09-01')]);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    public function test_login_with_bad_password(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => $user1->username,
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    public function test_login_with_non_existing_credentials(): void
    {
        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => 'bad',
            'password' => 'bad',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    public function test_login_with_no_json(): void
    {
        $this->client->jsonRequest('POST', '/api/login_check');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title' => 'An error occurred',
            'status' => 400,
            'detail' => 'Bad Request',
        ]);
    }
}

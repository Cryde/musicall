<?php

declare(strict_types=1);

namespace App\Tests\Api\Security;

use App\Entity\User;
use App\Service\Procedure\User\DeleteAccountProcedure;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\JwtPayload;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The JWT names its user by id (#1134). A username can be changed and then taken by another account,
 * so a token naming its user by username would authenticate as whoever holds that name.
 */
#[ResetDatabase]
class JwtUserIdClaimTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_jwt_keeps_its_account_after_another_account_takes_the_old_username(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@example.com']);
        $jwt = $this->jwtManager()->create($alice);

        $this->rename($alice, 'alice_renamed');
        UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'taker@example.com']);

        $this->client->request('GET', '/api/users/self', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSelf',
            '@id' => '/api/users/self',
            '@type' => 'UserSelf',
            'id' => $alice->id,
            'username' => 'alice_renamed',
            'email' => 'alice@example.com',
            'roles' => ['ROLE_USER'],
            'profile_picture' => null,
            'username_changed_datetime' => null,
            'has_password' => true,
            'locale' => 'fr',
            'has_mobile_app' => false,
        ]);
    }

    /** The old username ends up held by nobody, which used to leave the token resolving to no one. */
    public function test_a_jwt_keeps_its_account_after_the_old_username_changes_hands_twice(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@example.com']);
        $jwt = $this->jwtManager()->create($alice);

        $this->rename($alice, 'alice_renamed');
        $taker = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'taker@example.com']);
        $this->rename($taker, 'taker_renamed');

        $this->client->request('GET', '/api/users/self', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSelf',
            '@id' => '/api/users/self',
            '@type' => 'UserSelf',
            'id' => $alice->id,
            'username' => 'alice_renamed',
            'email' => 'alice@example.com',
            'roles' => ['ROLE_USER'],
            'profile_picture' => null,
            'username_changed_datetime' => null,
            'has_password' => true,
            'locale' => 'fr',
            'has_mobile_app' => false,
        ]);
    }

    /** The deleted row keeps its id, so the token would find it: refused as the unknown user it used to name. */
    public function test_a_jwt_issued_before_the_account_was_deleted_is_refused(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@example.com']);
        $jwt = $this->jwtManager()->create($alice);

        static::getContainer()->get(DeleteAccountProcedure::class)->process($alice);

        $this->client->request('GET', '/api/users/self', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Identifiants invalides.',
        ]);
    }

    /** The JWT UsernameChangeJwtSubscriber hands back after a rename is a working one. */
    public function test_the_jwt_reissued_after_a_rename_authenticates(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@example.com']);

        $this->client->jsonRequest('POST', '/api/users/change_username', ['newUsername' => 'alice_renamed'], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jwtManager()->create($alice),
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $cookies = [];
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = (string) $cookie->getValue();
        }
        $reissued = $cookies['jwt_hp'] . '.' . $cookies['jwt_s'];
        $this->assertSame(['roles' => ['ROLE_USER'], 'id' => $alice->id, 'username' => 'alice_renamed'], array_intersect_key(JwtPayload::of($reissued), ['roles' => 0, 'id' => 0, 'username' => 0]));

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/api/users/self', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $reissued]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserSelf',
            '@id' => '/api/users/self',
            '@type' => 'UserSelf',
            'id' => $alice->id,
            'username' => 'alice_renamed',
            'email' => 'alice@example.com',
            'roles' => ['ROLE_USER'],
            'profile_picture' => null,
            'username_changed_datetime' => $alice->usernameChangedDatetime?->format(\DateTimeInterface::ATOM),
            'has_password' => true,
            'locale' => 'fr',
            'has_mobile_app' => false,
        ]);
    }

    /** A token issued before the deploy carries only the username: refused, the client renews it. */
    public function test_a_jwt_naming_its_user_by_username_only_is_refused(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@example.com']);

        /** @var JWTEncoderInterface $encoder */
        $encoder = static::getContainer()->get(JWTEncoderInterface::class);
        $jwt = $encoder->encode(['roles' => $alice->getRoles(), 'username' => 'alice']);

        $this->client->request('GET', '/api/users/self', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'Unable to find key "id" in the token payload.',
        ]);
    }

    /** The web reads username and roles out of the token to show who is signed in. */
    public function test_an_issued_jwt_carries_the_id_the_username_and_the_roles(): void
    {
        $alice = UserFactory::new()->asBaseUser()->create(['username' => 'alice', 'email' => 'alice@example.com']);

        $this->client->jsonRequest('POST', '/api/native/login_check', [
            'username' => 'alice',
            'password' => 'password',
        ]);

        $this->assertResponseIsSuccessful();
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $claims = JwtPayload::of($body['token']);

        $this->assertIsInt($claims['iat'] ?? null);
        $this->assertIsInt($claims['exp'] ?? null);
        $this->assertIsString($claims['jti'] ?? null);
        unset($claims['iat'], $claims['exp'], $claims['jti']);
        $this->assertSame([
            'roles' => ['ROLE_USER'],
            'id' => $alice->id,
            'username' => 'alice',
        ], $claims);
    }

    private function jwtManager(): JWTTokenManagerInterface
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class);
    }

    private function rename(User $user, string $username): void
    {
        $user->username = $username;
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}

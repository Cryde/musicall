<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Mercure\MercureTopic;
use App\Repository\Notification\NotificationRepository;
use App\Repository\User\EmailVerificationCodeRepository;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\EmailVerificationCodeFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class RegisterConfirmCodeTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private function hashCode(string $plainCode): string
    {
        /** @var PasswordHasherFactoryInterface $factory */
        $factory = static::getContainer()->get(PasswordHasherFactoryInterface::class);

        return $factory->getPasswordHasher(User::class)->hash($plainCode);
    }

    public function test_verify_code_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => $this->hashCode('123456'),
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // user is now confirmed
        $userRefreshed = static::getContainer()->get(UserRepository::class)->find($user->id);
        $this->assertNotNull($userRefreshed->confirmationDatetime);

        // verification code is marked as used
        $verificationCodeRepository = static::getContainer()->get(EmailVerificationCodeRepository::class);
        $verificationCode = $verificationCodeRepository->findOneBy(['user' => $user]);
        $this->assertNotNull($verificationCode->usedDatetime);

        // welcome email was sent
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailTextBodyContains($email, 'Bienvenue sur MusicAll');
        $this->assertEmailHeaderSame($email, 'templateId', '6');
        $this->assertEmailAddressContains($email, 'From', 'no-reply@musicall.com');
        $this->assertEmailAddressContains($email, 'To', 'user@email.com');
    }

    /** A password sign-up joins the bands its email was invited to once that email is confirmed (#1353). */
    public function test_confirming_an_invited_email_joins_the_band(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'band_admin', 'email' => 'admin@example.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin]);
        $invitation = BandSpaceInvitationFactory::new()->create(['bandSpace' => $bandSpace, 'invitedBy' => $admin, 'email' => 'user@email.com']);
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'nouveau',
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);
        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => $this->hashCode('123456'),
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('null', $this->client->getResponse()->getContent());

        // Before bandOf(), which clears the entity manager and so detaches $admin.
        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0);
        $this->assertSame([NotificationType::BandSpaceInvitationAccepted], array_map(static fn ($notification) => $notification->type, $notifications));
        $this->assertSame([
            'members' => [['band_admin', 'admin', 'active'], ['nouveau', 'user', 'active']],
            'invitation' => ['accepted', 'nouveau'],
        ], $this->bandOf((string) $invitation->id));
        $this->assertEqualsCanonicalizing([
            MercureTopic::userNotifications((string) $admin->id),
            MercureTopic::userNotifications((string) $user->id),
        ], $this->changeSignalTopics(), 'The band hears that its members changed');
    }

    public function test_verify_code_invalid_code(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => $this->hashCode('123456'),
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '999999',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'invalid_code',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'invalid_code',
        ]);

        // attempts incremented
        $verificationCodeRepository = static::getContainer()->get(EmailVerificationCodeRepository::class);
        $verificationCode = $verificationCodeRepository->findOneBy(['user' => $user]);
        $this->assertSame(1, $verificationCode->attempts);

        $this->assertEmailCount(0);
    }

    public function test_verify_code_expired(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => $this->hashCode('123456'),
            'expirationDatetime' => new DateTimeImmutable('-1 minute'),
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'code_expired',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'code_expired',
        ]);

        $this->assertEmailCount(0);
    }

    public function test_verify_code_max_attempts_reached(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => $this->hashCode('123456'),
            'attempts' => 5,
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'max_attempts_reached',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'max_attempts_reached',
        ]);

        $this->assertEmailCount(0);
    }

    public function test_verify_code_no_code_found(): void
    {
        UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'no_code_found',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'no_code_found',
        ]);

        $this->assertEmailCount(0);
    }

    public function test_verify_code_already_verified_user_returns_generic_no_code_found(): void
    {
        // Anti-enumeration: an already-verified account must not be distinguishable
        // from an unknown one, so it returns the same generic `no_code_found` rather
        // than disclosing `already_verified`.
        $user = UserFactory::new()->asBaseUser()->create([
            'email' => 'user@email.com',
        ]);

        // The lingering unused code is deliberate: it proves the verified-account
        // guard fires before verify() is reached, so the code is never consumed.
        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => $this->hashCode('123456'),
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'no_code_found',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'no_code_found',
        ]);

        $this->assertEmailCount(0);
    }

    public function test_verify_code_unknown_email(): void
    {
        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'unknown@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'no_code_found',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'no_code_found',
        ]);

        $this->assertEmailCount(0);
    }

    public function test_verify_code_validation_errors(): void
    {
        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => '',
            'code' => '',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=4b6f5c76-22b4-409d-af16-fbe823ba9332',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => "email: Saisis un email\ncode: Saisis le code\ncode: Le code doit contenir 6 chiffres",
            'status' => 422,
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=4b6f5c76-22b4-409d-af16-fbe823ba9332',
            'description' => "email: Saisis un email\ncode: Saisis le code\ncode: Le code doit contenir 6 chiffres",
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'Saisis un email',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'code',
                    'message' => 'Saisis le code',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'code',
                    'message' => 'Le code doit contenir 6 chiffres',
                    'code' => '4b6f5c76-22b4-409d-af16-fbe823ba9332',
                ],
            ],
        ]);

        $this->assertEmailCount(0);
    }

    public function test_verify_code_rate_limited(): void
    {
        // No user fixture needed: the limiter is consumed before any DB lookup,
        // so the request 429s whether or not the account exists.
        // Burn the full 10/hour budget for this IP up front so the single request
        // below is the one that trips the limiter (the test client's IP is 127.0.0.1).
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.email_verification_check');
        $limiter->create('127.0.0.1')->consume(10);

        $this->client->jsonRequest('POST', '/api/email/verify/check', [
            'email' => 'user@email.com',
            'code' => '123456',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);
    }

    /**
     * The invitation's band read back from the database: its members, and what became of the invitation.
     *
     * @return array{members: list<array{string, string, string}>, invitation: array{string, ?string}}
     */
    private function bandOf(string $invitationId): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $invitation = $entityManager->find(BandSpaceInvitation::class, $invitationId);
        $this->assertInstanceOf(BandSpaceInvitation::class, $invitation);
        $members = array_map(
            static fn (BandSpaceMembership $membership): array => [$membership->user->username, $membership->role->value, $membership->status->value],
            $entityManager->getRepository(BandSpaceMembership::class)->findBy(['bandSpace' => $invitation->bandSpace]),
        );
        sort($members);

        return ['members' => $members, 'invitation' => [$invitation->status->value, $invitation->existingUser?->username]];
    }

    /** @return list<string> */
    private function changeSignalTopics(): array
    {
        $updates = array_filter(
            self::getContainer()->get(RecordingHub::class)->updates,
            static fn (Update $update): bool => str_contains($update->getData(), '"band_space_changed"'),
        );

        return array_merge(...array_map(static fn (Update $update): array => $update->getTopics(), array_values($updates)));
    }
}

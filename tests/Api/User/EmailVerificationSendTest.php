<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Repository\User\EmailVerificationCodeRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\EmailVerificationCodeFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class EmailVerificationSendTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_send_code(): void
    {
        UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/send', [
            'email' => 'user@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // a verification code was created
        $verificationCodeRepository = static::getContainer()->get(EmailVerificationCodeRepository::class);
        $this->assertCount(1, $verificationCodeRepository->findAll());

        // OTP email was sent
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailTextBodyContains($email, 'Votre code de vérification');
        $this->assertEmailHeaderSame($email, 'templateId', '10');
        $this->assertEmailAddressContains($email, 'To', 'user@email.com');
    }

    public function test_send_code_invalidates_previous_codes(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        // create an old code (cooldown expired)
        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => 'old_hash',
            'creationDatetime' => new DateTimeImmutable('-5 minutes'),
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/send', [
            'email' => 'user@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // old code invalidated, new code created
        $verificationCodeRepository = static::getContainer()->get(EmailVerificationCodeRepository::class);
        $allCodes = $verificationCodeRepository->findAll();
        $this->assertCount(2, $allCodes);

        // only the new code is unused
        $unusedCode = $verificationCodeRepository->findLatestUnusedForUser($user);
        $this->assertNotNull($unusedCode);
        $this->assertSame(0, $unusedCode->attempts);
    }

    public function test_send_code_cooldown_not_expired(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'confirmationDatetime' => null,
            'email' => 'user@email.com',
        ]);

        // create a recent code (cooldown NOT expired)
        EmailVerificationCodeFactory::createOne([
            'user' => $user,
            'hashedCode' => 'recent_hash',
            'creationDatetime' => new DateTimeImmutable('-30 seconds'),
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/send', [
            'email' => 'user@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        // Anti-enumeration: a not-yet-expired cooldown returns the same silent 201 as
        // the unknown/verified paths, so it cannot reveal that the account exists.
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // No new code generated and no email sent while on cooldown.
        $verificationCodeRepository = static::getContainer()->get(EmailVerificationCodeRepository::class);
        $this->assertCount(1, $verificationCodeRepository->findAll());
        $this->assertEmailCount(0);
    }

    public function test_send_code_unknown_email_silent(): void
    {
        $this->client->jsonRequest('POST', '/api/email/verify/send', [
            'email' => 'unknown@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        // silent success to avoid email enumeration
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertEmailCount(0);
    }

    public function test_send_code_already_verified_user_silent(): void
    {
        UserFactory::new()->asBaseUser()->create([
            'email' => 'user@email.com',
        ]);

        $this->client->jsonRequest('POST', '/api/email/verify/send', [
            'email' => 'user@email.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        // silent success to avoid email enumeration
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->assertEmailCount(0);
    }

    public function test_send_code_validation_errors(): void
    {
        $this->client->jsonRequest('POST', '/api/email/verify/send', [
            'email' => '',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'email: Veuillez saisir un email',
            'status' => 422,
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'description' => 'email: Veuillez saisir un email',
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'Veuillez saisir un email',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
        ]);
    }

    public function test_send_code_rate_limited(): void
    {
        // No user fixture needed: the limiter is consumed before any DB lookup,
        // so the request 429s whether or not the account exists.
        // Burn the full 10/hour budget for this IP up front (test client IP is 127.0.0.1).
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.email_verification_send');
        $limiter->create('127.0.0.1')->consume(10);

        $this->client->jsonRequest('POST', '/api/email/verify/send', [
            'email' => 'user@email.com',
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

        $this->assertEmailCount(0);
    }
}

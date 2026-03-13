<?php

declare(strict_types=1);

namespace App\Tests\Api\User;

use App\Entity\User;
use App\Repository\User\EmailVerificationCodeRepository;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\EmailVerificationCodeFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RegisterConfirmCodeTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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
        $userRefreshed = static::getContainer()->get(UserRepository::class)->find($user->getId());
        $this->assertNotNull($userRefreshed->getConfirmationDatetime());

        // verification code is marked as used
        $verificationCodeRepository = static::getContainer()->get(EmailVerificationCodeRepository::class);
        $verificationCode = $verificationCodeRepository->findOneBy(['user' => $user->_real()]);
        $this->assertNotNull($verificationCode->usedDatetime);

        // welcome email was sent
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailTextBodyContains($email, 'Bienvenue sur MusicAll');
        $this->assertEmailHeaderSame($email, 'templateId', '6');
        $this->assertEmailAddressContains($email, 'From', 'no-reply@musicall.com');
        $this->assertEmailAddressContains($email, 'To', 'user@email.com');
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
        $verificationCode = $verificationCodeRepository->findOneBy(['user' => $user->_real()]);
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

    public function test_verify_code_already_verified_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
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
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'already_verified',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'already_verified',
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
            'detail' => "email: Veuillez saisir un email\ncode: Veuillez saisir le code\ncode: Le code doit contenir 6 chiffres",
            'status' => 422,
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=4b6f5c76-22b4-409d-af16-fbe823ba9332',
            'description' => "email: Veuillez saisir un email\ncode: Veuillez saisir le code\ncode: Le code doit contenir 6 chiffres",
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'Veuillez saisir un email',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'code',
                    'message' => 'Veuillez saisir le code',
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
}

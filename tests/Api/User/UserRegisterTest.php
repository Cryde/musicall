<?php

namespace App\Tests\Api\User;

use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\User\EmailVerificationCodeRepository;
use App\Repository\UserRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserRegisterTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_register(): void
    {
        $userRepository = static::getContainer()->get(UserRepository::class);
        $verificationCodeRepository = static::getContainer()->get(EmailVerificationCodeRepository::class);

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
        $this->assertSame('super_username', $results[0]->username);
        $this->assertSame('super_email@mail.com', $results[0]->email);
        $this->assertNotSame('password', $results[0]->password); // we assert that we don't record plain text password in db
        $this->assertNull($results[0]->confirmationDatetime); // email not yet confirmed

        // a verification code was created
        $verificationCode = $verificationCodeRepository->findLatestUnusedForUser($results[0]);
        $this->assertNotNull($verificationCode);
        $this->assertSame(0, $verificationCode->attempts);
        $this->assertFalse($verificationCode->isExpired());
        $this->assertFalse($verificationCode->isUsed());

        // OTP email was sent
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailTextBodyContains($email, 'Ton code de vérification');
        $this->assertEmailHeaderSame($email, 'templateId', '10');
        $this->assertEmailAddressContains($email, 'From', 'no-reply@musicall.com');
        $this->assertEmailAddressContains($email, 'To', 'super_email@mail.com');
    }

    public function test_register_stores_the_locale_the_request_resolved(): void
    {
        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'super_username',
            'password' => 'password',
            'email' => 'super_email@mail.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_ACCEPT_LANGUAGE' => 'en']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['username' => 'super_username']);
        $this->assertSame('en', $user?->locale);
        // The verification code goes out in the stored locale's template.
        $this->assertEmailCount(1);
        $this->assertEmailHeaderSame($this->getMailerMessage(), 'templateId', '19');
    }


    public function test_a_web_sign_up_is_recorded_as_web_with_a_password(): void
    {
        $this->register([]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('null', $this->client->getResponse()->getContent());
        $this->assertSame(['web', 'password', null], $this->registrationOf('super_username'));
    }

    /** @param array<string, string> $headers */
    #[DataProvider('appHeaders')]
    public function test_an_app_sign_up_records_the_app_and_its_version(array $headers, array $expected): void
    {
        $this->register($headers);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('null', $this->client->getResponse()->getContent());
        $this->assertSame($expected, $this->registrationOf('super_username'));
    }

    public static function appHeaders(): iterable
    {
        yield 'ios' => [['HTTP_X_CLIENT_PLATFORM' => 'ios', 'HTTP_X_CLIENT_VERSION' => '1.13.0'], ['ios', 'password', '1.13.0']];
        yield 'android with a build number' => [['HTTP_X_CLIENT_PLATFORM' => 'android', 'HTTP_X_CLIENT_VERSION' => '1.13.0+42'], ['android', 'password', '1.13.0+42']];
        yield 'android without a version' => [['HTTP_X_CLIENT_PLATFORM' => 'android'], ['android', 'password', null]];
    }

    /** The headers are client supplied and only feed statistics: anything unexpected is dropped, never refused. */
    #[DataProvider('invalidAppHeaders')]
    public function test_an_invalid_client_header_is_ignored(array $headers, array $expected): void
    {
        $this->register($headers);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('null', $this->client->getResponse()->getContent());
        $this->assertSame($expected, $this->registrationOf('super_username'));
    }

    public static function invalidAppHeaders(): iterable
    {
        yield 'unknown platform' => [['HTTP_X_CLIENT_PLATFORM' => 'windows', 'HTTP_X_CLIENT_VERSION' => '1.13.0'], ['web', 'password', null]];
        yield 'platform in another case' => [['HTTP_X_CLIENT_PLATFORM' => 'IOS', 'HTTP_X_CLIENT_VERSION' => '1.13.0'], ['web', 'password', null]];
        yield 'platform a server value' => [['HTTP_X_CLIENT_PLATFORM' => 'unknown_app'], ['web', 'password', null]];
        yield 'version without a platform' => [['HTTP_X_CLIENT_VERSION' => '1.13.0'], ['web', 'password', null]];
        yield 'version not a version' => [['HTTP_X_CLIENT_PLATFORM' => 'ios', 'HTTP_X_CLIENT_VERSION' => "1.13.0'; DROP TABLE fos_user"], ['ios', 'password', null]];
        yield 'version too long' => [['HTTP_X_CLIENT_PLATFORM' => 'ios', 'HTTP_X_CLIENT_VERSION' => '1.13.0+' . str_repeat('9', 40)], ['ios', 'password', null]];
        yield 'version with a trailing newline' => [['HTTP_X_CLIENT_PLATFORM' => 'android', 'HTTP_X_CLIENT_VERSION' => "1.13.0\n"], ['android', 'password', null]];
    }

    /**
     * Anyone can register with an address they do not own (#1353): the invitation waits for the email to
     * be confirmed, so the band gets no unconfirmed member and the real owner still finds it pending.
     */
    public function test_registering_an_invited_email_does_not_join_the_band_before_it_is_confirmed(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'band_admin', 'email' => 'admin@example.com']);
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'The Rockers']);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin]);
        $invitation = BandSpaceInvitationFactory::new()->create(['bandSpace' => $bandSpace, 'invitedBy' => $admin, 'email' => 'super_email@mail.com']);

        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'super_username',
            'password' => 'password',
            'email' => 'super_email@mail.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('null', $this->client->getResponse()->getContent());

        $this->assertSame([
            'members' => [['band_admin', 'admin', 'active']],
            'invitation' => ['pending', null],
        ], $this->bandOf((string) $invitation->id));
        $this->assertSame([], self::getContainer()->get(RecordingHub::class)->updates);
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
password: Le mot de passe doit au moins contenir 8 caractères',
            'status' => 422,
            'type' => '/validation_errors/0=9ff3fdc4-b214-49db-8718-39c315e33d45;1=bd79c0ab-ddba-46cc-a703-a7a4b08de310;2=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'description' => 'username: Le nom d\'utilisateur doit au moins contenir 3 caractères
email: Email invalide
password: Le mot de passe doit au moins contenir 8 caractères',
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
            'message' => 'Le mot de passe doit au moins contenir 8 caractères',
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

    /**
     * « Cet email est déjà utilisé » tells whether an address has an account, so once the attempts
     * are spent the answer is a 429 and never that verdict (#1119).
     */
    public function test_an_exhausted_attempt_budget_answers_before_saying_an_email_is_taken(): void
    {
        UserFactory::new()->create(['username' => 'existing', 'email' => 'taken@mail.com']);
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.registration_attempt');
        $limiter->create('127.0.0.1')->consume(20);

        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'curious_user',
            'password' => 'password',
            'email' => 'taken@mail.com',
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
            'detail' => 'Tu es déjà connecté',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'Tu es déjà connecté',
        ]);

        $this->assertEmailCount(0);
    }

    /** @param array<string, string> $headers */
    private function register(array $headers): void
    {
        $this->client->jsonRequest('POST', '/api/users/register', [
            'username' => 'super_username',
            'password' => 'password',
            'email' => 'super_email@mail.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json', ...$headers]);
    }

    /** @return array{string, string, ?string} the platform, method and app version stored for the account */
    private function registrationOf(string $username): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        return [$user->registrationPlatform->value, $user->registrationMethod->value, $user->registrationAppVersion];
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
}

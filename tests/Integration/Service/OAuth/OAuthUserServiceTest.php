<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\OAuth;

use App\Entity\SocialAccount;
use App\Exception\OAuth\OAuthEmailExistsException;
use App\Repository\SocialAccountRepository;
use App\Repository\UserRepository;
use App\Service\OAuth\OAuthUserData;
use App\Service\OAuth\OAuthUserService;
use App\Service\OAuth\ProfilePictureImporter;
use App\Tests\Factory\User\SocialAccountFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class OAuthUserServiceTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    public function test_find_or_create_user_returns_existing_user_when_social_account_exists(): void
    {
        $existingUser = UserFactory::new()->asBaseUser()->create()->_real();
        SocialAccountFactory::new()->create([
            'user' => $existingUser,
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'providerId' => 'google-id-123',
            'email' => 'test@example.com',
        ]);

        // pretest
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-123',
            email: 'test@example.com',
            username: 'Test User',
            pictureUrl: null,
        );

        $result = $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);

        $this->assertSame($existingUser->getId(), $result->user->getId());
        $this->assertFalse($result->isNew);
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());
    }

    public function test_find_or_create_user_links_social_account_to_logged_in_user(): void
    {
        $currentUser = UserFactory::new()->asBaseUser()->create()->_real();

        // pretest
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-456',
            email: 'oauth@example.com',
            username: 'OAuth User',
            pictureUrl: null,
        );

        $result = $this->getOAuthUserService()->findOrCreateUser(
            $userData,
            SocialAccount::PROVIDER_GOOGLE,
            $currentUser
        );

        $this->assertSame($currentUser->getId(), $result->user->getId());
        $this->assertFalse($result->isNew);
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());

        $socialAccount = $this->getSocialAccountRepository()->findByProviderAndProviderId(
            SocialAccount::PROVIDER_GOOGLE,
            'google-id-456'
        );
        $this->assertNotNull($socialAccount);
        $this->assertSame($currentUser->getId(), $socialAccount->getUser()->getId());
    }

    public function test_find_or_create_user_throws_exception_when_email_exists(): void
    {
        UserFactory::new()->create([
            'email' => 'existing@example.com',
            'username' => 'existing_user',
        ]);

        // pretest
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-789',
            email: 'existing@example.com',
            username: 'New User',
            pictureUrl: null,
        );

        try {
            $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);
            $this->fail('Expected OAuthEmailExistsException was not thrown');
        } catch (OAuthEmailExistsException) {
            $this->assertSame(1, $this->getUserRepository()->count());
            $this->assertSame(0, $this->getSocialAccountRepository()->count());
        }
    }

    public function test_find_or_create_user_creates_new_user(): void
    {
        $this->mockProfilePictureImporter();

        // pretest
        $this->assertSame(0, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-new',
            email: 'newuser@example.com',
            username: 'New User',
            pictureUrl: null,
        );

        $result = $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);

        $this->assertTrue($result->isNew);
        $this->assertSame('newuser@example.com', $result->user->getEmail());
        $this->assertSame('newuser', $result->user->getUsername());
        $this->assertNull($result->user->getPassword());
        $this->assertNotNull($result->user->getConfirmationDatetime());
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());

        $socialAccount = $this->getSocialAccountRepository()->findByProviderAndProviderId(
            SocialAccount::PROVIDER_GOOGLE,
            'google-id-new'
        );
        $this->assertNotNull($socialAccount);
        $this->assertSame($result->user->getId(), $socialAccount->getUser()->getId());
    }

    public function test_find_or_create_user_sanitizes_username(): void
    {
        $this->mockProfilePictureImporter();

        // pretest
        $this->assertSame(0, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-special',
            email: 'special@example.com',
            username: 'John Döe @Special!',
            pictureUrl: null,
        );

        $result = $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);

        $this->assertSame('johndoespecial', $result->user->getUsername());
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());
    }

    public function test_find_or_create_user_ensures_unique_username(): void
    {
        $this->mockProfilePictureImporter();

        UserFactory::new()->create(['username' => 'johndoe']);

        // pretest
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-duplicate',
            email: 'johndoe2@example.com',
            username: 'John Doe',
            pictureUrl: null,
        );

        $result = $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);

        $this->assertSame('johndoe1', $result->user->getUsername());
        $this->assertSame(2, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());
    }

    public function test_find_or_create_user_imports_profile_picture(): void
    {
        $mock = $this->createMock(ProfilePictureImporter::class);
        $mock->expects($this->once())
            ->method('importFromUrl')
            ->with(
                $this->callback(fn ($user) => $user->getEmail() === 'withpicture@example.com'),
                'https://example.com/picture.jpg'
            );

        self::getContainer()->set(ProfilePictureImporter::class, $mock);

        // pretest
        $this->assertSame(0, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-picture',
            email: 'withpicture@example.com',
            username: 'User With Picture',
            pictureUrl: 'https://example.com/picture.jpg',
        );

        $result = $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);

        $this->assertTrue($result->isNew);
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());
    }

    public function test_find_or_create_user_handles_empty_username(): void
    {
        $this->mockProfilePictureImporter();

        // pretest
        $this->assertSame(0, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-empty',
            email: 'empty@example.com',
            username: '!!!',
            pictureUrl: null,
        );

        $result = $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);

        $this->assertSame('user', $result->user->getUsername());
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());
    }

    public function test_find_or_create_user_handles_empty_username_but_username_exists(): void
    {
        UserFactory::new()->create(['username' => 'user']);
        $this->mockProfilePictureImporter();

        // pretest
        $this->assertSame(1, $this->getUserRepository()->count());
        $this->assertSame(0, $this->getSocialAccountRepository()->count());

        $userData = new OAuthUserData(
            id: 'google-id-empty',
            email: 'empty@example.com',
            username: '!!!',
            pictureUrl: null,
        );

        $result = $this->getOAuthUserService()->findOrCreateUser($userData, SocialAccount::PROVIDER_GOOGLE);

        $this->assertSame('user1', $result->user->getUsername());
        $this->assertSame(2, $this->getUserRepository()->count());
        $this->assertSame(1, $this->getSocialAccountRepository()->count());
    }


    private function mockProfilePictureImporter(): void
    {
        $stub = $this->createStub(ProfilePictureImporter::class);
        self::getContainer()->set(ProfilePictureImporter::class, $stub);
    }

    private function getOAuthUserService(): OAuthUserService
    {
        return self::getContainer()->get(OAuthUserService::class);
    }

    private function getSocialAccountRepository(): SocialAccountRepository
    {
        return self::getContainer()->get(SocialAccountRepository::class);
    }

    private function getUserRepository(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }
}

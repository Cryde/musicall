<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Length;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserProfilePatchTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_patch_profile_update_bio(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'patchuser',
            'email' => 'patchuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('Original bio');
        $profile->setLocation('Original location');
        $profile->setIsPublic(true);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'bio' => 'Updated bio content',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'bio' => 'Updated bio content',
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_update_location(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'locationuser',
            'email' => 'locationuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('My bio');
        $profile->setLocation('Old City');
        $profile->setIsPublic(true);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => 'New City, Country',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'location' => 'New City, Country',
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_update_is_public(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'visibilityuser',
            'email' => 'visibilityuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setIsPublic(true);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'is_public' => false,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'is_public' => false,
        ]);
    }

    public function test_patch_profile_update_multiple_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'multiuser',
            'email' => 'multiuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('Old bio');
        $profile->setLocation('Old location');
        $profile->setIsPublic(true);
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'bio' => 'New bio',
            'location' => 'New location',
            'is_public' => false,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'bio' => 'New bio',
            'location' => 'New location',
            'is_public' => false,
        ]);
    }

    public function test_patch_profile_bio_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'longbiouser',
            'email' => 'longbiouser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'bio' => str_repeat('a', 2001),
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . Length::TOO_LONG_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'bio',
                    'message' => 'La bio ne doit pas dépasser 2000 caractères',
                    'code' => Length::TOO_LONG_ERROR,
                ],
            ],
            'detail' => 'bio: La bio ne doit pas dépasser 2000 caractères',
            'description' => 'bio: La bio ne doit pas dépasser 2000 caractères',
            'type' => '/validation_errors/' . Length::TOO_LONG_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_patch_profile_location_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'longlocationuser',
            'email' => 'longlocationuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => str_repeat('a', 256),
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . Length::TOO_LONG_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'location',
                    'message' => 'La localisation ne doit pas dépasser 255 caractères',
                    'code' => Length::TOO_LONG_ERROR,
                ],
            ],
            'detail' => 'location: La localisation ne doit pas dépasser 255 caractères',
            'description' => 'location: La localisation ne doit pas dépasser 255 caractères',
            'type' => '/validation_errors/' . Length::TOO_LONG_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_patch_profile_set_bio_to_null(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'nullbiouser',
            'email' => 'nullbiouser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('Existing bio');
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'bio' => null,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'is_public' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Entity\User\UserProfile;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserProfilePatchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_patch_profile_update_display_name(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'displaynameuser',
            'email' => 'displaynameuser@test.com',
        ]);
        $profile = $user->profile;
        $profile->displayName = 'Original Name';
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'display_name' => 'Jean Dupont',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'display_name' => 'Jean Dupont',
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_update_bio(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'patchuser',
            'email' => 'patchuser@test.com',
        ]);
        $profile = $user->profile;
        $profile->bio = 'Original bio';
        $profile->location = 'Original location';
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'bio' => 'Updated bio content',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'bio' => 'Updated bio content',
            'location' => 'Original location',
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_update_location(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'locationuser',
            'email' => 'locationuser@test.com',
        ]);
        $profile = $user->profile;
        $profile->bio = 'My bio';
        $profile->location = 'Old City';
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => 'New City, Country',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'bio' => 'My bio',
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
        $profile = $user->profile;
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
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

    public function test_patch_profile_left_out_visibility_keeps_a_private_profile_private(): void
    {
        $profile = new UserProfile();
        $profile->isPublic = false;
        $user = UserFactory::new()->asBaseUser()->create(['profile' => $profile]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'display_name' => 'Jean Dupont',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'display_name' => 'Jean Dupont',
            'is_public' => false,
        ]);
    }

    public function test_patch_profile_update_multiple_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'multiuser',
            'email' => 'multiuser@test.com',
        ]);
        $profile = $user->profile;
        $profile->bio = 'Old bio';
        $profile->location = 'Old location';
        $profile->isPublic = true;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
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

    public function test_patch_profile_display_name_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'longdisplaynameuser',
            'email' => 'longdisplaynameuser@test.com',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'display_name' => str_repeat('a', 101),
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . Length::TOO_LONG_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'display_name',
                    'message' => 'Le nom d\'affichage ne doit pas dépasser 100 caractères',
                    'code' => Length::TOO_LONG_ERROR,
                ],
            ],
            'detail' => 'display_name: Le nom d\'affichage ne doit pas dépasser 100 caractères',
            'description' => 'display_name: Le nom d\'affichage ne doit pas dépasser 100 caractères',
            'type' => '/validation_errors/' . Length::TOO_LONG_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_patch_profile_bio_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'longbiouser',
            'email' => 'longbiouser@test.com',
        ]);

        $this->client->loginUser($user);
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

        $this->client->loginUser($user);
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
        $profile = $user->profile;
        $profile->bio = 'Existing bio';
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
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

    public function test_patch_profile_location_with_a_picked_city(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => 'Liège',
            'latitude' => 50.6451,
            'longitude' => 5.5736,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'location' => 'Liège',
            'latitude' => 50.6451,
            'longitude' => 5.5736,
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_other_field_keeps_the_city_coordinates(): void
    {
        $profile = new UserProfile();
        $profile->location = 'Liège';
        $profile->latitude = 50.6451;
        $profile->longitude = 5.5736;
        $user = UserFactory::new()->asBaseUser()->create(['profile' => $profile]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'bio' => 'Batteur',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'bio' => 'Batteur',
            'location' => 'Liège',
            'latitude' => 50.6451,
            'longitude' => 5.5736,
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_retyped_location_drops_the_previous_city_coordinates(): void
    {
        $profile = new UserProfile();
        $profile->location = 'Liège';
        $profile->latitude = 50.6451;
        $profile->longitude = 5.5736;
        $user = UserFactory::new()->asBaseUser()->create(['profile' => $profile]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => 'Quelque part en Wallonie',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'location' => 'Quelque part en Wallonie',
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_cleared_location_drops_the_city_coordinates(): void
    {
        $profile = new UserProfile();
        $profile->location = 'Liège';
        $profile->latitude = 50.6451;
        $profile->longitude = 5.5736;
        $user = UserFactory::new()->asBaseUser()->create(['profile' => $profile]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => null,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserProfileEdit',
            '@id' => '/api/user/profile',
            '@type' => 'UserProfileEdit',
            'is_public' => true,
        ]);
    }

    public function test_patch_profile_one_coordinate_alone_is_refused(): void
    {
        $profile = new UserProfile();
        $profile->location = 'Liège';
        $profile->latitude = 50.6451;
        $profile->longitude = 5.5736;
        $user = UserFactory::new()->asBaseUser()->create(['profile' => $profile]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'latitude' => 50.9,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Choisissez une ville dans la liste pour enregistrer sa position',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Choisissez une ville dans la liste pour enregistrer sa position',
        ]);
    }

    public function test_patch_profile_latitude_without_longitude(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => 'Liège',
            'latitude' => 50.6451,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Choisissez une ville dans la liste pour enregistrer sa position',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Choisissez une ville dans la liste pour enregistrer sa position',
        ]);
    }

    public function test_patch_profile_coordinates_without_a_location(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'latitude' => 50.6451,
            'longitude' => 5.5736,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Choisissez une ville dans la liste pour enregistrer sa position',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Choisissez une ville dans la liste pour enregistrer sa position',
        ]);
    }

    public function test_patch_profile_latitude_out_of_range(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/profile', [
            'location' => 'Liège',
            'latitude' => 91,
            'longitude' => 5.5736,
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'latitude',
                    'message' => 'La latitude doit être comprise entre -90 et 90',
                    'code' => Range::NOT_IN_RANGE_ERROR,
                ],
            ],
            'detail' => 'latitude: La latitude doit être comprise entre -90 et 90',
            'description' => 'latitude: La latitude doit être comprise entre -90 et 90',
            'type' => '/validation_errors/' . Range::NOT_IN_RANGE_ERROR,
            'title' => 'An error occurred',
        ]);
    }
}

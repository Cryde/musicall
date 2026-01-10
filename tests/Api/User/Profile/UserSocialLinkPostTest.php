<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Enum\SocialPlatform;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserSocialLinkFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserSocialLinkPostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_post_social_link_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'newlinkuser',
            'email' => 'newlinkuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'youtube',
            'url' => 'https://www.youtube.com/@mychannel',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            '@type' => 'UserSocialLinkResource',
            'platform' => 'youtube',
            'platform_label' => 'YouTube',
            'url' => 'https://www.youtube.com/@mychannel',
        ]);
    }

    public function test_post_social_link_youtube(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'youtubeuser',
            'email' => 'youtubeuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'youtube',
            'url' => 'https://www.youtube.com/@user',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            '@type' => 'UserSocialLinkResource',
            'platform' => 'youtube',
            'platform_label' => 'YouTube',
        ]);
    }

    public function test_post_social_link_soundcloud(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'soundclouduser',
            'email' => 'soundclouduser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'soundcloud',
            'url' => 'https://soundcloud.com/user',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            '@type' => 'UserSocialLinkResource',
            'platform' => 'soundcloud',
            'platform_label' => 'SoundCloud',
        ]);
    }

    public function test_post_social_link_instagram(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'instagramuser',
            'email' => 'instagramuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'instagram',
            'url' => 'https://www.instagram.com/user',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            '@type' => 'UserSocialLinkResource',
            'platform' => 'instagram',
            'platform_label' => 'Instagram',
        ]);
    }

    public function test_post_social_link_twitter(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'twitteruser',
            'email' => 'twitteruser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'twitter',
            'url' => 'https://twitter.com/user',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            '@type' => 'UserSocialLinkResource',
            'platform' => 'twitter',
            'platform_label' => 'X (Twitter)',
        ]);
    }

    public function test_post_social_link_website(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'websiteuser',
            'email' => 'websiteuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'website',
            'url' => 'https://www.mywebsite.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonContains([
            '@type' => 'UserSocialLinkResource',
            'platform' => 'website',
            'platform_label' => 'Site web',
        ]);
    }

    public function test_post_social_link_invalid_platform(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'invalidplatform',
            'email' => 'invalidplatform@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'invalidplatform',
            'url' => 'https://www.example.com',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 400,
            'type' => '/errors/400',
            'detail' => 'Plateforme invalide',
            'description' => 'Plateforme invalide',
        ]);
    }

    public function test_post_social_link_invalid_url(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'invalidurluser',
            'email' => 'invalidurluser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'youtube',
            'url' => 'not-a-valid-url',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . Url::INVALID_URL_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'L\'URL n\'est pas valide',
                    'code' => Url::INVALID_URL_ERROR,
                ],
            ],
            'detail' => 'url: L\'URL n\'est pas valide',
            'description' => 'url: L\'URL n\'est pas valide',
            'type' => '/validation_errors/' . Url::INVALID_URL_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_post_social_link_empty_url(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'emptyurluser',
            'email' => 'emptyurluser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'youtube',
            'url' => '',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'L\'URL est requise',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
            ],
            'detail' => 'url: L\'URL est requise',
            'description' => 'url: L\'URL est requise',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_post_social_link_empty_platform(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'emptyplatformuser',
            'email' => 'emptyplatformuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => '',
            'url' => 'https://www.youtube.com/@user',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'platform',
                    'message' => 'La plateforme est requise',
                    'code' => NotBlank::IS_BLANK_ERROR,
                ],
            ],
            'detail' => 'platform: La plateforme est requise',
            'description' => 'platform: La plateforme est requise',
            'type' => '/validation_errors/' . NotBlank::IS_BLANK_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_post_social_link_duplicate_platform(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'duplicateuser',
            'email' => 'duplicateuser@test.com',
        ]);
        $profile = $user->getProfile();

        UserSocialLinkFactory::new()->create([
            'profile' => $profile,
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@existingchannel',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'youtube',
            'url' => 'https://www.youtube.com/@newchannel',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 409,
            'type' => '/errors/409',
            'detail' => 'Un lien pour cette plateforme existe déjà',
            'description' => 'Un lien pour cette plateforme existe déjà',
        ]);
    }

    public function test_post_social_link_url_too_long(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'longurluser',
            'email' => 'longurluser@test.com',
        ]);

        $longUrl = 'https://www.youtube.com/' . str_repeat('a', 500);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/profile/social-links', [
            'platform' => 'youtube',
            'url' => $longUrl,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@type' => 'ConstraintViolation',
            '@id' => '/api/validation_errors/' . Length::TOO_LONG_ERROR,
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'L\'URL ne doit pas dépasser 500 caractères',
                    'code' => Length::TOO_LONG_ERROR,
                ],
            ],
            'detail' => 'url: L\'URL ne doit pas dépasser 500 caractères',
            'description' => 'url: L\'URL ne doit pas dépasser 500 caractères',
            'type' => '/validation_errors/' . Length::TOO_LONG_ERROR,
            'title' => 'An error occurred',
        ]);
    }
}

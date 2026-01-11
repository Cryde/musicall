<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Profile;

use App\Entity\Musician\MusicianAnnounce;
use App\Enum\SocialPlatform;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserSocialLinkFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicProfileTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_public_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'publicuser',
            'email' => 'publicuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('Test bio content');
        $profile->setLocation('Paris, France');
        $profile->setIsPublic(true);
        $user->_save();

        $this->client->request('GET', '/api/user/profile/publicuser');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/publicuser',
            '@type' => 'PublicProfile',
            'username' => 'publicuser',
            'user_id' => $user->getId(),
            'bio' => 'Test bio content',
            'location' => 'Paris, France',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
        ]);
    }

    public function test_get_public_profile_with_social_links(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'socialuser',
            'email' => 'socialuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio('Musician bio');
        $profile->setIsPublic(true);
        $user->_save();

        UserSocialLinkFactory::new()->create([
            'profile' => $profile,
            'platform' => SocialPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/@socialuser',
        ]);

        $this->client->request('GET', '/api/user/profile/socialuser');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/socialuser',
            '@type' => 'PublicProfile',
            'username' => 'socialuser',
            'user_id' => $user->getId(),
            'bio' => 'Musician bio',
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [
                [
                    '@type' => 'PublicProfileSocialLink',
                    'platform' => 'youtube',
                    'platform_label' => 'YouTube',
                    'url' => 'https://www.youtube.com/@socialuser',
                ],
            ],
            'musician_announces' => [],
            'has_musician_profile' => false,
        ]);
    }

    public function test_get_public_profile_with_musician_announces(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'musician',
            'email' => 'musician@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setIsPublic(true);
        $user->_save();

        $instrument = InstrumentFactory::new()->asGuitar()->create();
        $style = StyleFactory::new()->create(['name' => 'Rock']);

        $announce = MusicianAnnounceFactory::new()->create([
            'author' => $user,
            'instrument' => $instrument,
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'locationName' => 'Paris',
            'styles' => [$style],
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-15T10:30:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/musician');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/musician',
            '@type' => 'PublicProfile',
            'username' => 'musician',
            'user_id' => $user->getId(),
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [],
            'musician_announces' => [
                [
                    '@type' => 'PublicProfileAnnounce',
                    'id' => $announce->_real()->getId(),
                    'creation_datetime' => '2024-01-15T10:30:00+00:00',
                    'type' => MusicianAnnounce::TYPE_MUSICIAN,
                    'instrument_name' => 'Guitariste',
                    'location_name' => 'Paris',
                    'styles' => ['Rock'],
                ],
            ],
            'has_musician_profile' => false,
        ]);
    }

    public function test_get_public_profile_not_found(): void
    {
        $this->client->request('GET', '/api/user/profile/nonexistentuser');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Profil non trouvé',
            'description' => 'Profil non trouvé',
        ]);
    }

    public function test_get_public_profile_private_returns_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'privateuser',
            'email' => 'privateuser@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setIsPublic(false);
        $user->_save();

        $this->client->request('GET', '/api/user/profile/privateuser');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Ce profil est privé',
            'description' => 'Ce profil est privé',
        ]);
    }

    public function test_get_public_profile_with_empty_bio_and_location(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'emptyprofile',
            'email' => 'emptyprofile@test.com',
        ]);
        $profile = $user->getProfile();
        $profile->setBio(null);
        $profile->setLocation(null);
        $profile->setIsPublic(true);
        $user->_save();

        $this->client->request('GET', '/api/user/profile/emptyprofile');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicProfile',
            '@id' => '/api/user/profile/emptyprofile',
            '@type' => 'PublicProfile',
            'username' => 'emptyprofile',
            'user_id' => $user->getId(),
            'member_since' => '1990-01-02T02:03:04+00:00',
            'social_links' => [],
            'musician_announces' => [],
            'has_musician_profile' => false,
        ]);
    }
}

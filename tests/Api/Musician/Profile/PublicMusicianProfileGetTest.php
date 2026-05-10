<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile;

use App\Enum\Musician\AvailabilityStatus;
use App\Enum\Musician\SkillLevel;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileInstrumentFactory;
use App\Tests\Factory\Musician\MusicianProfileMediaFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class PublicMusicianProfileGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_public_musician_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'publicmusicianuser',
            'email' => 'publicmusicianuser@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::AVAILABLE_FOR_SESSIONS,
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
            'styles' => [$rock],
        ]);

        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::ADVANCED,
        ]);

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $musicianAnnounce = MusicianAnnounceFactory::new()->asBand()->create([
            'author' => $user,
            'instrument' => $drum,
            'locationName' => 'Paris',
            'creationDatetime' => new \DateTime('2024-06-01T12:00:00+00:00'),
            'styles' => [$rock, $jazz],
        ]);

        $media1 = MusicianProfileMediaFactory::new()->asYouTube()->create([
            'musicianProfile' => $musicianProfile,
            'title' => 'My YouTube Video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'embedId' => 'abc123',
            'position' => 0,
        ]);

        $media2 = MusicianProfileMediaFactory::new()->asSpotify()->create([
            'musicianProfile' => $musicianProfile,
            'title' => 'My Spotify Track',
            'url' => 'https://open.spotify.com/track/xyz789',
            'embedId' => 'track/xyz789',
            'position' => 1,
        ]);

        $this->client->request('GET', '/api/user/profile/publicmusicianuser/musician');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicMusicianProfile',
            '@id' => '/api/user/profile/publicmusicianuser/musician',
            '@type' => 'PublicMusicianProfile',
            'username' => 'publicmusicianuser',
            'user_id' => $user->id,
            'availability_status' => 'available_for_sessions',
            'availability_status_label' => 'Disponible pour sessions/concerts',
            'instruments' => [
                [
                    '@type' => 'PublicMusicianProfileInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'advanced',
                    'skill_level_label' => 'Avancé',
                ],
            ],
            'styles' => [
                [
                    '@type' => 'PublicMusicianProfileStyle',
                    'id' => $rock->id,
                    'name' => 'Rock',
                ],
            ],
            'musician_announces' => [
                [
                    '@type' => 'PublicProfileAnnounce',
                    'id' => $musicianAnnounce->id,
                    'creation_datetime' => '2024-06-01T12:00:00+00:00',
                    'type' => 2,
                    'instrument_name' => 'Batteur',
                    'location_name' => 'Paris',
                    'styles' => ['Rock', 'Jazz'],
                ],
            ],
            'media' => [
                [
                    '@type' => 'MusicianProfileMedia',
                    'id' => $media1->id,
                    'platform' => 'youtube',
                    'platform_label' => 'YouTube',
                    'url' => 'https://www.youtube.com/watch?v=abc123',
                    'embed_id' => 'abc123',
                    'title' => 'My YouTube Video',
                    'position' => 0,
                ],
                [
                    '@type' => 'MusicianProfileMedia',
                    'id' => $media2->id,
                    'platform' => 'spotify',
                    'platform_label' => 'Spotify',
                    'url' => 'https://open.spotify.com/track/xyz789',
                    'embed_id' => 'track/xyz789',
                    'title' => 'My Spotify Track',
                    'position' => 1,
                ],
            ],
            'creation_datetime' => '2024-01-15T10:00:00+00:00',
        ]);
    }

    public function test_get_public_musician_profile_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'minimalmusicianuser',
            'email' => 'minimalmusicianuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => null,
            'creationDatetime' => new \DateTimeImmutable('2024-02-20T14:30:00+00:00'),
            'styles' => [],
        ]);

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->request('GET', '/api/user/profile/minimalmusicianuser/musician');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicMusicianProfile',
            '@id' => '/api/user/profile/minimalmusicianuser/musician',
            '@type' => 'PublicMusicianProfile',
            'username' => 'minimalmusicianuser',
            'user_id' => $user->id,
            'instruments' => [],
            'styles' => [],
            'musician_announces' => [],
            'media' => [],
            'creation_datetime' => '2024-02-20T14:30:00+00:00',
        ]);
    }

    public function test_get_public_musician_profile_user_not_found(): void
    {
        $this->client->request('GET', '/api/user/profile/nonexistentuser/musician');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@type' => 'Error',
            '@id' => '/api/errors/404',
            'title' => 'An error occurred',
            'detail' => 'Utilisateur non trouvé',
            'description' => 'Utilisateur non trouvé',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_get_public_musician_profile_not_found(): void
    {
        UserFactory::new()->asBaseUser()->create([
            'username' => 'userwithoutmusicianprofile',
            'email' => 'userwithoutmusicianprofile@test.com',
        ]);

        $this->client->request('GET', '/api/user/profile/userwithoutmusicianprofile/musician');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@type' => 'Error',
            '@id' => '/api/errors/404',
            'title' => 'An error occurred',
            'detail' => 'Profil musicien non trouvé',
            'description' => 'Profil musicien non trouvé',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_get_public_musician_profile_accessible_without_authentication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'publicaccessuser',
            'email' => 'publicaccessuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
            'creationDatetime' => new \DateTimeImmutable('2024-03-10T08:00:00+00:00'),
            'styles' => [],
        ]);

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        // No login - public endpoint
        $this->client->request('GET', '/api/user/profile/publicaccessuser/musician');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicMusicianProfile',
            '@id' => '/api/user/profile/publicaccessuser/musician',
            '@type' => 'PublicMusicianProfile',
            'username' => 'publicaccessuser',
            'user_id' => $user->id,
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [],
            'styles' => [],
            'musician_announces' => [],
            'media' => [],
            'creation_datetime' => '2024-03-10T08:00:00+00:00',
        ]);
    }
}

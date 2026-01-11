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
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicMusicianProfileGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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
            'styles' => [$rock->_real()],
        ]);

        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::ADVANCED,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $musicianAnnounce = MusicianAnnounceFactory::new()->asBand()->create([
            'author' => $user->_real(),
            'instrument' => $drum,
            'locationName' => 'Paris',
            'creationDatetime' => new \DateTime('2024-06-01T12:00:00+00:00'),
            'styles' => [$rock->_real(), $jazz->_real()],
        ]);

        $this->client->request('GET', '/api/user/profile/publicmusicianuser/musician');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicMusicianProfile',
            '@id' => '/api/user/profile/publicmusicianuser/musician',
            '@type' => 'PublicMusicianProfile',
            'username' => 'publicmusicianuser',
            'user_id' => $user->getId(),
            'availability_status' => 'available_for_sessions',
            'availability_status_label' => 'Disponible pour sessions/concerts',
            'instruments' => [
                [
                    '@type' => 'PublicMusicianProfileInstrument',
                    'instrument_id' => $guitar->getId(),
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'advanced',
                    'skill_level_label' => 'Avancé',
                ],
            ],
            'styles' => [
                [
                    '@type' => 'PublicMusicianProfileStyle',
                    'id' => $rock->getId(),
                    'name' => 'Rock',
                ],
            ],
            'musician_announces' => [
                [
                    '@type' => 'PublicProfileAnnounce',
                    'id' => $musicianAnnounce->getId(),
                    'creation_datetime' => '2024-06-01T12:00:00+00:00',
                    'type' => 2,
                    'instrument_name' => 'Batteur',
                    'location_name' => 'Paris',
                    'styles' => ['Rock', 'Jazz'],
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

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->request('GET', '/api/user/profile/minimalmusicianuser/musician');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicMusicianProfile',
            '@id' => '/api/user/profile/minimalmusicianuser/musician',
            '@type' => 'PublicMusicianProfile',
            'username' => 'minimalmusicianuser',
            'user_id' => $user->getId(),
            'instruments' => [],
            'styles' => [],
            'musician_announces' => [],
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
        $user = UserFactory::new()->asBaseUser()->create([
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

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        // No login - public endpoint
        $this->client->request('GET', '/api/user/profile/publicaccessuser/musician');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicMusicianProfile',
            '@id' => '/api/user/profile/publicaccessuser/musician',
            '@type' => 'PublicMusicianProfile',
            'username' => 'publicaccessuser',
            'user_id' => $user->getId(),
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [],
            'styles' => [],
            'musician_announces' => [],
            'creation_datetime' => '2024-03-10T08:00:00+00:00',
        ]);
    }
}

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
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianProfileGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_own_musician_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'musicianuser',
            'email' => 'musicianuser@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
            'styles' => [$rock->_real()],
        ]);

        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::ADVANCED,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();
        $profileId = $musicianProfile->_real()->getId();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/musician-profile');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $profileId,
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $guitar->getId(),
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'advanced',
                    'skill_level_label' => 'Avancé',
                ],
            ],
            'style_ids' => [],
            'styles' => [
                [
                    '@type' => 'MusicianProfileEditStyle',
                    'id' => $rock->getId(),
                    'name' => 'Rock',
                ],
            ],
        ]);
    }

    public function test_get_own_musician_profile_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'minimaluser',
            'email' => 'minimaluser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => null,
            'styles' => [],
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();
        $profileId = $musicianProfile->_real()->getId();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/musician-profile');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $profileId,
            'instruments' => [],
            'style_ids' => [],
            'styles' => [],
        ]);
    }

    public function test_get_own_musician_profile_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'noprofileuser',
            'email' => 'noprofileuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/musician-profile');

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

    public function test_get_own_musician_profile_unauthorized(): void
    {
        $this->client->request('GET', '/api/user/musician-profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

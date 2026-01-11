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

class MusicianProfilePatchTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_patch_musician_profile_update_availability_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'patchuser',
            'email' => 'patchuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
            'styles' => [],
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();
        $musicianProfile->_refresh();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'availability_status' => 'not_available',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->getId(),
            'availability_status' => 'not_available',
            'availability_status_label' => 'Non disponible',
            'instruments' => [],
            'style_ids' => [],
            'styles' => [],
        ]);
    }

    public function test_patch_musician_profile_update_instruments(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'instrumentpatchuser',
            'email' => 'instrumentpatchuser@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
            'styles' => [],
        ]);

        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::BEGINNER,
        ]);

        $musicianProfile->_refresh();
        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [
                [
                    'instrument_id' => $drum->getId(),
                    'skill_level' => 'professional',
                ],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->getId(),
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $drum->getId(),
                    'instrument_name' => 'Batteur',
                    'skill_level' => 'professional',
                    'skill_level_label' => 'Professionnel',
                ],
            ],
            'style_ids' => [],
            'styles' => [],
        ]);
    }

    public function test_patch_musician_profile_update_styles(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'stylepatchuser',
            'email' => 'stylepatchuser@test.com',
        ]);

        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        $metal = StyleFactory::new()->asMetal()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => null,
            'styles' => [$rock->_real()],
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();
        $musicianProfile->_refresh();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'style_ids' => [$jazz->getId(), $metal->getId()],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->getId(),
            'instruments' => [],
            'style_ids' => [],
            'styles' => [
                [
                    '@type' => 'MusicianProfileEditStyle',
                    'id' => $jazz->getId(),
                    'name' => 'Jazz',
                ],
                [
                    '@type' => 'MusicianProfileEditStyle',
                    'id' => $metal->getId(),
                    'name' => 'Metal',
                ],
            ],
        ]);
    }

    public function test_patch_musician_profile_clear_instruments(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'clearinstrumentsuser',
            'email' => 'clearinstrumentsuser@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::OPEN_TO_COLLABORATIONS,
            'styles' => [],
        ]);

        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::ADVANCED,
        ]);

        $musicianProfile->_refresh();
        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->getId(),
            'availability_status' => 'open_to_collaborations',
            'availability_status_label' => 'Ouvert aux collaborations',
            'instruments' => [],
            'style_ids' => [],
            'styles' => [],
        ]);
    }

    public function test_patch_musician_profile_nonexistent_instrument_id_ignored(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'nonexistentinstrumentuser',
            'email' => 'nonexistentinstrumentuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => null,
            'styles' => [],
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();
        $musicianProfile->_refresh();

        $nonExistentId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [
                [
                    'instrument_id' => $nonExistentId,
                    'skill_level' => 'advanced',
                ],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->getId(),
            'instruments' => [],
            'style_ids' => [],
            'styles' => [],
        ]);
    }

    public function test_patch_musician_profile_nonexistent_style_id_ignored(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'nonexistentstyleuser',
            'email' => 'nonexistentstyleuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => null,
            'styles' => [],
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();
        $musicianProfile->_refresh();

        $nonExistentId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'style_ids' => [$nonExistentId],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->getId(),
            'instruments' => [],
            'style_ids' => [],
            'styles' => [],
        ]);
    }

    public function test_patch_musician_profile_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'noprofileuser',
            'email' => 'noprofileuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'availability_status' => 'looking_for_band',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

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

    public function test_patch_musician_profile_unauthorized(): void
    {
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'availability_status' => 'looking_for_band',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}

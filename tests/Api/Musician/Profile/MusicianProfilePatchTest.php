<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianProfile;
use App\Entity\User;
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
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class MusicianProfilePatchTest extends ApiTestCase
{
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

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);
        \Zenstruck\Foundry\Persistence\refresh($musicianProfile);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'availability_status' => 'not_available',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->id,
            'availability_status' => 'not_available',
            'availability_status_label' => 'Non disponible',
            'instruments' => [],
            'styles' => [],
            'bands' => [],
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

        \Zenstruck\Foundry\Persistence\refresh($musicianProfile);
        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [
                [
                    'instrument_id' => $drum->id,
                    'skill_level' => 'professional',
                ],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->id,
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $drum->id,
                    'instrument_name' => 'Batteur',
                    'skill_level' => 'professional',
                    'skill_level_label' => 'Professionnel',
                ],
            ],
            'styles' => [],
            'bands' => [],
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
            'styles' => [$rock],
        ]);

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);
        \Zenstruck\Foundry\Persistence\refresh($musicianProfile);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'style_ids' => [$jazz->id, $metal->id],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->id,
            'instruments' => [],
            'styles' => [
                [
                    '@type' => 'MusicianProfileEditStyle',
                    'id' => $jazz->id,
                    'name' => 'Jazz',
                ],
                [
                    '@type' => 'MusicianProfileEditStyle',
                    'id' => $metal->id,
                    'name' => 'Metal',
                ],
            ],
            'bands' => [],
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

        \Zenstruck\Foundry\Persistence\refresh($musicianProfile);
        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->id,
            'availability_status' => 'open_to_collaborations',
            'availability_status_label' => 'Ouvert aux collaborations',
            'instruments' => [],
            'styles' => [],
            'bands' => [],
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

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);
        \Zenstruck\Foundry\Persistence\refresh($musicianProfile);

        $nonExistentId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->client->loginUser($user);
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
            'id' => $musicianProfile->id,
            'instruments' => [],
            'styles' => [],
            'bands' => [],
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

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);
        \Zenstruck\Foundry\Persistence\refresh($musicianProfile);

        $nonExistentId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'style_ids' => [$nonExistentId],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->id,
            'instruments' => [],
            'styles' => [],
            'bands' => [],
        ]);
    }

    public function test_patch_musician_profile_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'noprofileuser',
            'email' => 'noprofileuser@test.com',
        ]);

        $this->client->loginUser($user);
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

    public function test_patch_musician_profile_without_instruments_and_styles_keeps_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'keepuser',
            'email' => 'keepuser@test.com',
        ]);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
            'styles' => [$rock],
        ]);
        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::ADVANCED,
        ]);
        \Zenstruck\Foundry\Persistence\refresh($musicianProfile);
        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'availability_status' => 'not_available',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->id,
            'availability_status' => 'not_available',
            'availability_status_label' => 'Non disponible',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'advanced',
                    'skill_level_label' => 'Avancé',
                ],
            ],
            'styles' => [
                [
                    '@type' => 'MusicianProfileEditStyle',
                    'id' => $rock->id,
                    'name' => 'Rock',
                ],
            ],
            'bands' => [],
        ]);
    }

    public function test_patch_musician_profile_with_empty_style_ids_clears_styles(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'clearstyleuser',
            'email' => 'clearstyleuser@test.com',
        ]);
        $rock = StyleFactory::new()->asRock()->create();

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => null,
            'styles' => [$rock],
        ]);
        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'style_ids' => [],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $musicianProfile->id,
            'instruments' => [],
            'styles' => [],
            'bands' => [],
        ]);
    }

    // The profile's pencils (#1317) each send one block: whatever it leaves out keeps its value.

    public function test_sending_only_the_availability_keeps_the_other_blocks(): void
    {
        [$user, $profile, $guitar, $rock] = $this->musicianWithEveryBlock();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'availability_status' => 'not_available',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $profile->id,
            'availability_status' => 'not_available',
            'availability_status_label' => 'Non disponible',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'advanced',
                    'skill_level_label' => 'Avancé',
                ],
            ],
            'styles' => [
                ['@type' => 'MusicianProfileEditStyle', 'id' => $rock->id, 'name' => 'Rock'],
            ],
            'bands' => [
                ['@type' => 'MusicianProfileBand', 'name' => 'Ardent', 'current' => true],
            ],
        ]);
    }

    public function test_sending_only_the_instruments_keeps_the_other_blocks(): void
    {
        [$user, $profile, , $rock] = $this->musicianWithEveryBlock();
        $drum = InstrumentFactory::new()->asDrum()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [
                ['instrument_id' => $drum->id, 'skill_level' => 'intermediate'],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $profile->id,
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $drum->id,
                    'instrument_name' => 'Batteur',
                    'skill_level' => 'intermediate',
                    'skill_level_label' => 'Intermédiaire',
                ],
            ],
            'styles' => [
                ['@type' => 'MusicianProfileEditStyle', 'id' => $rock->id, 'name' => 'Rock'],
            ],
            'bands' => [
                ['@type' => 'MusicianProfileBand', 'name' => 'Ardent', 'current' => true],
            ],
        ]);
    }

    public function test_sending_only_the_styles_keeps_the_other_blocks(): void
    {
        [$user, $profile, $guitar] = $this->musicianWithEveryBlock();
        $jazz = StyleFactory::new()->asJazz()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'style_ids' => [$jazz->id],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $profile->id,
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'advanced',
                    'skill_level_label' => 'Avancé',
                ],
            ],
            'styles' => [
                ['@type' => 'MusicianProfileEditStyle', 'id' => $jazz->id, 'name' => 'Jazz'],
            ],
            'bands' => [
                ['@type' => 'MusicianProfileBand', 'name' => 'Ardent', 'current' => true],
            ],
        ]);
    }

    public function test_sending_only_the_bands_keeps_the_other_blocks(): void
    {
        [$user, $profile, $guitar, $rock] = $this->musicianWithEveryBlock();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'bands' => [
                ['name' => 'Lueur', 'current' => false],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $profile->id,
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $guitar->id,
                    'instrument_name' => 'Guitariste',
                    'skill_level' => 'advanced',
                    'skill_level_label' => 'Avancé',
                ],
            ],
            'styles' => [
                ['@type' => 'MusicianProfileEditStyle', 'id' => $rock->id, 'name' => 'Rock'],
            ],
            'bands' => [
                ['@type' => 'MusicianProfileBand', 'name' => 'Lueur', 'current' => false],
            ],
        ]);
    }

    /**
     * @return array{0: User, 1: MusicianProfile, 2: Instrument, 3: Style}
     */
    private function musicianWithEveryBlock(): array
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'blockuser', 'email' => 'blockuser@test.com']);
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $profile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
            'styles' => [$rock],
            'bands' => [['name' => 'Ardent', 'style_id' => null, 'role' => null, 'current' => true, 'url' => null]],
        ]);
        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $profile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::ADVANCED,
        ]);
        \Zenstruck\Foundry\Persistence\refresh($profile);
        $user->musicianProfile = $profile;
        \Zenstruck\Foundry\Persistence\save($user);

        return [$user, $profile, $guitar, $rock];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile;

use App\Entity\Musician\MusicianProfile;
use App\Enum\Musician\AvailabilityStatus;
use App\Enum\Musician\SkillLevel;
use App\Repository\Musician\MusicianProfileRepository;
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

class MusicianProfileDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_musician_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleteprofileuser',
            'email' => 'deleteprofileuser@test.com',
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
        $this->client->request('DELETE', '/api/user/musician-profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify profile is deleted in database
        /** @var MusicianProfileRepository $repository */
        $repository = static::getContainer()->get(MusicianProfileRepository::class);
        $deletedProfile = $repository->find($profileId);
        $this->assertNull($deletedProfile);
    }

    public function test_delete_musician_profile_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'noprofileuser',
            'email' => 'noprofileuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('DELETE', '/api/user/musician-profile');

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

    public function test_delete_musician_profile_unauthorized(): void
    {
        $this->client->request('DELETE', '/api/user/musician-profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}

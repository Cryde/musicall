<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile;

use App\Entity\Musician\MusicianProfile;
use App\Enum\Musician\AvailabilityStatus;
use App\Enum\Musician\SkillLevel;
use App\Repository\Musician\MusicianProfileMediaRepository;
use App\Repository\Musician\MusicianProfileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileInstrumentFactory;
use App\Tests\Factory\Musician\MusicianProfileMediaFactory;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Metric\ViewFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class MusicianProfileDeleteTest extends ApiTestCase
{
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
            'styles' => [$rock],
        ]);

        MusicianProfileInstrumentFactory::new()->create([
            'musicianProfile' => $musicianProfile,
            'instrument' => $guitar,
            'skillLevel' => SkillLevel::ADVANCED,
        ]);

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $profileId = $musicianProfile->id;

        $this->client->loginUser($user);
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

        $this->client->loginUser($user);
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

    public function test_delete_musician_profile_cascade_deletes_media(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'cascadedeleteuser',
            'email' => 'cascadedeleteuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $media1 = MusicianProfileMediaFactory::new()->asYouTube()->create([
            'musicianProfile' => $musicianProfile,
            'position' => 0,
        ]);

        $media2 = MusicianProfileMediaFactory::new()->asSpotify()->create([
            'musicianProfile' => $musicianProfile,
            'position' => 1,
        ]);

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $profileId = $musicianProfile->id;
        $media1Id = $media1->id;
        $media2Id = $media2->id;

        /** @var MusicianProfileMediaRepository $mediaRepository */
        $mediaRepository = static::getContainer()->get(MusicianProfileMediaRepository::class);
        $this->assertCount(2, $mediaRepository->findBy(['musicianProfile' => $profileId]));

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/musician-profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify profile is deleted
        /** @var MusicianProfileRepository $profileRepository */
        $profileRepository = static::getContainer()->get(MusicianProfileRepository::class);
        $this->assertNull($profileRepository->find($profileId));

        // Verify media are cascade deleted
        $this->assertNull($mediaRepository->find($media1Id));
        $this->assertNull($mediaRepository->find($media2Id));
    }

    public function test_delete_musician_profile_with_views_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'viewsdeleteuser',
            'email' => 'viewsdeleteuser@test.com',
        ]);

        $viewCache = ViewCacheFactory::new()->create(['count' => 5]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'viewCache' => $viewCache,
        ]);

        // Create views associated with the profile's viewCache
        ViewFactory::new()->create([
            'viewCache' => $viewCache,
            'identifier' => 'view1',
        ]);

        ViewFactory::new()->create([
            'viewCache' => $viewCache,
            'identifier' => 'view2',
        ]);

        $user->musicianProfile = $musicianProfile;
        \Zenstruck\Foundry\Persistence\save($user);

        $profileId = $musicianProfile->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/musician-profile');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verify profile is deleted in database
        /** @var MusicianProfileRepository $repository */
        $repository = static::getContainer()->get(MusicianProfileRepository::class);
        $deletedProfile = $repository->find($profileId);
        $this->assertNull($deletedProfile);
    }
}

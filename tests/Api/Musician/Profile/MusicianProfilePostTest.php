<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile;

use App\Enum\Musician\AvailabilityStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianProfilePostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_create_musician_profile_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'newmusicianuser',
            'email' => 'newmusicianuser@test.com',
        ]);

        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/musician-profile', [
            'availability_status' => 'looking_for_band',
            'instruments' => [
                [
                    'instrument_id' => $guitar->getId(),
                    'skill_level' => 'advanced',
                ],
            ],
            'style_ids' => [$rock->getId(), $jazz->getId()],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Get the created profile ID from response
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $profileId = $response['id'];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $profileId,
            'availability_status' => 'looking_for_band',
            'instruments' => [
                [
                    '@type' => 'MusicianProfileEditInstrument',
                    'instrument_id' => $guitar->getId(),
                    'skill_level' => 'advanced',
                ],
            ],
            'style_ids' => [$rock->getId(), $jazz->getId()],
            'styles' => [],
        ]);
    }

    public function test_create_musician_profile_minimal(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'minimalcreateuser',
            'email' => 'minimalcreateuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/musician-profile', [
            'availability_status' => null,
            'instruments' => [],
            'style_ids' => [],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $profileId = $response['id'];

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

    public function test_create_musician_profile_already_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'existingprofileuser',
            'email' => 'existingprofileuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'availabilityStatus' => AvailabilityStatus::LOOKING_FOR_BAND,
            'styles' => [],
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/musician-profile', [
            'availability_status' => 'not_available',
            'instruments' => [],
            'style_ids' => [],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@type' => 'Error',
            '@id' => '/api/errors/400',
            'title' => 'An error occurred',
            'detail' => 'Vous avez déjà un profil musicien',
            'description' => 'Vous avez déjà un profil musicien',
            'status' => 400,
            'type' => '/errors/400',
        ]);
    }

    public function test_create_musician_profile_unauthorized(): void
    {
        $this->client->jsonRequest('POST', '/api/user/musician-profile', [
            'availability_status' => 'looking_for_band',
            'instruments' => [],
            'style_ids' => [],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}

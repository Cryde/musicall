<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile\Media;

use App\Repository\Musician\MusicianProfileMediaRepository;
use App\Service\Google\DummyYoutubeRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileMediaFactory;
use App\Tests\Factory\User\UserFactory;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianProfileMediaPostTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        static::getContainer()->set(CacheManager::class, $this->buildCacheManagerMock());
    }

    public function test_create_media_success_youtube(): void
    {
        $mediaRepository = self::getContainer()->get(MusicianProfileMediaRepository::class);

        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'mediapostuser',
            'email' => 'mediapostuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            ['url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $media = $mediaRepository->findOneBy(['musicianProfile' => $musicianProfile->_real()]);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileMedia',
            '@id' => '/api/musician_profile_media/' . $media->getId(),
            '@type' => 'MusicianProfileMedia',
            'id' => $media->getId(),
            'platform' => 'youtube',
            'platform_label' => 'YouTube',
            'url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY,
            'embed_id' => DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY,
            'title' => 'Never Gonna Give You Up',
            'thumbnail_url' => 'http://musicall.test/test_media.jpg',
            'position' => 0,
        ]);
    }

    public function test_create_media_success_with_custom_title(): void
    {
        $mediaRepository = self::getContainer()->get(MusicianProfileMediaRepository::class);

        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'customtitleuser',
            'email' => 'customtitleuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            [
                'url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY,
                'title' => 'My Custom Title',
            ],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $media = $mediaRepository->findOneBy(['musicianProfile' => $musicianProfile->_real()]);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileMedia',
            '@id' => '/api/musician_profile_media/' . $media->getId(),
            '@type' => 'MusicianProfileMedia',
            'id' => $media->getId(),
            'platform' => 'youtube',
            'platform_label' => 'YouTube',
            'url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY,
            'embed_id' => DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY,
            'title' => 'My Custom Title',
            'thumbnail_url' => 'http://musicall.test/test_media.jpg',
            'position' => 0,
        ]);
    }

    public function test_create_media_increments_position(): void
    {
        $mediaRepository = self::getContainer()->get(MusicianProfileMediaRepository::class);

        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'positionuser',
            'email' => 'positionuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        // Create first media
        MusicianProfileMediaFactory::new()->asYouTube()->create([
            'musicianProfile' => $musicianProfile->_real(),
            'position' => 0,
        ]);

        // Create second media
        MusicianProfileMediaFactory::new()->asSpotify()->create([
            'musicianProfile' => $musicianProfile->_real(),
            'position' => 1,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            ['url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_PROCEDURE_TEST],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $media = $mediaRepository->findOneBy(['musicianProfile' => $musicianProfile->_real(), 'position' => 2]);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileMedia',
            '@id' => '/api/musician_profile_media/' . $media->getId(),
            '@type' => 'MusicianProfileMedia',
            'id' => $media->getId(),
            'platform' => 'youtube',
            'platform_label' => 'YouTube',
            'url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_PROCEDURE_TEST,
            'embed_id' => DummyYoutubeRepository::VIDEO_ID_PROCEDURE_TEST,
            'title' => 'titre de la vidéo',
            'position' => 2,
        ]);
    }

    public function test_create_media_requires_authentication(): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            ['url' => 'https://www.youtube.com/watch?v=abc123'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_create_media_without_musician_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'noprofileuser',
            'email' => 'noprofileuser@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            ['url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

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

    public function test_create_media_without_url(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'nourluser',
            'email' => 'nourluser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'url: Cette valeur ne doit pas être vide.',
            'description' => 'url: Cette valeur ne doit pas être vide.',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_media_with_invalid_url(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'invalidurluser',
            'email' => 'invalidurluser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            ['url' => 'https://example.com/not-supported'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.',
                    'code' => 'music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
                ],
            ],
            'detail' => 'url: URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.',
            'description' => 'url: URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.',
            'type' => '/validation_errors/music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
            'title' => 'An error occurred',
        ]);
    }

    public function test_create_media_max_limit_reached(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'maxlimituser',
            'email' => 'maxlimituser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        // Create 6 media items (the max limit)
        for ($i = 0; $i < 6; $i++) {
            MusicianProfileMediaFactory::new()->asYouTube()->create([
                'musicianProfile' => $musicianProfile->_real(),
                'position' => $i,
            ]);
        }

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/user/musician-profile/media',
            ['url' => 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_7f8e9d0c-1a2b-3c4d-5e6f-7a8b9c0d1e2f',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => '',
                    'message' => 'Vous ne pouvez pas ajouter plus de 6 médias',
                    'code' => 'music_all_7f8e9d0c-1a2b-3c4d-5e6f-7a8b9c0d1e2f',
                ],
            ],
            'detail' => 'Vous ne pouvez pas ajouter plus de 6 médias',
            'description' => 'Vous ne pouvez pas ajouter plus de 6 médias',
            'type' => '/validation_errors/music_all_7f8e9d0c-1a2b-3c4d-5e6f-7a8b9c0d1e2f',
            'title' => 'An error occurred',
        ]);
    }

    private function buildCacheManagerMock(): CacheManager&Stub
    {
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager
            ->method('getBrowserPath')
            ->willReturn('http://musicall.test/test_media.jpg');

        return $cacheManager;
    }
}

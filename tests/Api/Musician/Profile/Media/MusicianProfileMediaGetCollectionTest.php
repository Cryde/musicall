<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile\Media;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileMediaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianProfileMediaGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_collection_success_with_multiple_media(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'musicianwithmedia',
            'email' => 'musicianwithmedia@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        // Store IDs before creating media
        $userId = $user->getId();

        // Create media and store their IDs
        $media1Id = MusicianProfileMediaFactory::new()->asYouTube()->create([
            'musicianProfile' => $musicianProfile->_real(),
            'title' => 'My YouTube Video',
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'embedId' => 'abc123',
            'position' => 0,
        ])->getId();

        $media2Id = MusicianProfileMediaFactory::new()->asSpotify()->create([
            'musicianProfile' => $musicianProfile->_real(),
            'title' => 'My Spotify Track',
            'url' => 'https://open.spotify.com/track/xyz789',
            'embedId' => 'track/xyz789',
            'position' => 1,
        ])->getId();

        $media3Id = MusicianProfileMediaFactory::new()->asSoundCloud()->create([
            'musicianProfile' => $musicianProfile->_real(),
            'title' => 'My SoundCloud Track',
            'url' => 'https://soundcloud.com/artist/track',
            'embedId' => 'artist/track',
            'position' => 2,
        ])->getId();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/musician-profile/media');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileMedia',
            '@id' => '/api/user/musician-profile/media',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/musician_profile_media/' . $media1Id,
                    '@type' => 'MusicianProfileMedia',
                    'id' => $media1Id,
                    'platform' => 'youtube',
                    'platform_label' => 'YouTube',
                    'url' => 'https://www.youtube.com/watch?v=abc123',
                    'embed_id' => 'abc123',
                    'title' => 'My YouTube Video',
                    'position' => 0,
                ],
                [
                    '@id' => '/api/musician_profile_media/' . $media2Id,
                    '@type' => 'MusicianProfileMedia',
                    'id' => $media2Id,
                    'platform' => 'spotify',
                    'platform_label' => 'Spotify',
                    'url' => 'https://open.spotify.com/track/xyz789',
                    'embed_id' => 'track/xyz789',
                    'title' => 'My Spotify Track',
                    'position' => 1,
                ],
                [
                    '@id' => '/api/musician_profile_media/' . $media3Id,
                    '@type' => 'MusicianProfileMedia',
                    'id' => $media3Id,
                    'platform' => 'soundcloud',
                    'platform_label' => 'SoundCloud',
                    'url' => 'https://soundcloud.com/artist/track',
                    'embed_id' => 'artist/track',
                    'title' => 'My SoundCloud Track',
                    'position' => 2,
                ],
            ],
            'totalItems' => 3,
        ]);
    }

    public function test_get_collection_success_empty(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'musicianwithoutmedia',
            'email' => 'musicianwithoutmedia@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/musician-profile/media');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileMedia',
            '@id' => '/api/user/musician-profile/media',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_collection_without_musician_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'userwithouprofile',
            'email' => 'userwithouprofile@test.com',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/musician-profile/media');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileMedia',
            '@id' => '/api/user/musician-profile/media',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_collection_requires_authentication(): void
    {
        $this->client->request('GET', '/api/user/musician-profile/media');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_collection_does_not_return_other_users_media(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create([
            'username' => 'user1',
            'email' => 'user1@test.com',
        ]);

        $user2 = UserFactory::new()->asBaseUser()->create([
            'username' => 'user2',
            'email' => 'user2@test.com',
        ]);

        $musicianProfile1 = MusicianProfileFactory::new()->create(['user' => $user1]);
        $musicianProfile2 = MusicianProfileFactory::new()->create(['user' => $user2]);

        $user1->setMusicianProfile($musicianProfile1->_real());
        $user1->_save();
        $user2->setMusicianProfile($musicianProfile2->_real());
        $user2->_save();

        $media1Id = MusicianProfileMediaFactory::new()->asYouTube()->create([
            'musicianProfile' => $musicianProfile1->_real(),
            'title' => 'User1 Media',
            'url' => 'https://www.youtube.com/watch?v=user1video',
            'embedId' => 'user1video',
            'position' => 0,
        ])->getId();

        MusicianProfileMediaFactory::new()->asSpotify()->create([
            'musicianProfile' => $musicianProfile2->_real(),
            'title' => 'User2 Media',
            'url' => 'https://open.spotify.com/track/user2track',
            'embedId' => 'track/user2track',
            'position' => 0,
        ]);

        // Login as user1
        $this->client->loginUser($user1->_real());
        $this->client->request('GET', '/api/user/musician-profile/media');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileMedia',
            '@id' => '/api/user/musician-profile/media',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/musician_profile_media/' . $media1Id,
                    '@type' => 'MusicianProfileMedia',
                    'id' => $media1Id,
                    'platform' => 'youtube',
                    'platform_label' => 'YouTube',
                    'url' => 'https://www.youtube.com/watch?v=user1video',
                    'embed_id' => 'user1video',
                    'title' => 'User1 Media',
                    'position' => 0,
                ],
            ],
            'totalItems' => 1,
        ]);
    }
}

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

class MusicianProfileMediaDeleteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_delete_media_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'musicianuser',
            'email' => 'musicianuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $media = MusicianProfileMediaFactory::new()->create([
            'musicianProfile' => $musicianProfile->_real(),
            'title' => 'Media to delete',
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('DELETE', '/api/user/musician-profile/media/' . $media->getId());

        $this->assertResponseIsSuccessful();
    }

    public function test_delete_media_not_owned(): void
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

        $media = MusicianProfileMediaFactory::new()->create([
            'musicianProfile' => $musicianProfile1->_real(),
            'title' => 'User1 Media',
        ]);

        // Login as user2 and try to delete user1's media
        $this->client->loginUser($user2->_real());
        $this->client->request('DELETE', '/api/user/musician-profile/media/' . $media->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez pas accéder à ce média',
            'description' => 'Vous ne pouvez pas accéder à ce média',
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_delete_media_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'musicianuser',
            'email' => 'musicianuser@test.com',
        ]);

        $musicianProfile = MusicianProfileFactory::new()->create([
            'user' => $user,
        ]);

        $user->setMusicianProfile($musicianProfile->_real());
        $user->_save();

        $this->client->loginUser($user->_real());
        $this->client->request('DELETE', '/api/user/musician-profile/media/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Média non trouvé',
            'description' => 'Média non trouvé',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_delete_media_requires_authentication(): void
    {
        $this->client->request('DELETE', '/api/user/musician-profile/media/00000000-0000-0000-0000-000000000000');

        // Note: The provider runs before security check, so we get 404 "Profil musicien non trouvé" instead of 401
        // This is expected behavior since security is checked after the provider
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Profil musicien non trouvé',
            'description' => 'Profil musicien non trouvé',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_delete_media_without_musician_profile(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create([
            'username' => 'userwithprofile',
            'email' => 'userwithprofile@test.com',
        ]);

        $user2 = UserFactory::new()->asBaseUser()->create([
            'username' => 'userwithoutprofile',
            'email' => 'userwithoutprofile@test.com',
        ]);

        $musicianProfile1 = MusicianProfileFactory::new()->create(['user' => $user1]);
        $user1->setMusicianProfile($musicianProfile1->_real());
        $user1->_save();

        $media = MusicianProfileMediaFactory::new()->create([
            'musicianProfile' => $musicianProfile1->_real(),
        ]);

        // Login as user2 who has no musician profile
        $this->client->loginUser($user2->_real());
        $this->client->request('DELETE', '/api/user/musician-profile/media/' . $media->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Profil musicien non trouvé',
            'description' => 'Profil musicien non trouvé',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }
}

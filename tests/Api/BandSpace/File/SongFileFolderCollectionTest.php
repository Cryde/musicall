<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\SongFileFolderFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SongFileFolderCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_one_song_lists_its_own_folders_by_name(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $drums = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Batterie', 'parent' => $stems]);
        SongFileFolderFactory::createOne(['song' => SongFactory::new(['bandSpace' => $bandSpace]), 'createdBy' => $user, 'name' => 'Démos']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', [], self::JSON);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongFileFolder',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $drums->id,
                    '@type' => 'SongFileFolder',
                    'id' => $drums->id,
                    'band_space_id' => $bandSpace->id,
                    'song_id' => $song->id,
                    'parent_id' => $stems->id,
                    'name' => 'Batterie',
                    'creation_datetime' => '2026-11-20T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id,
                    '@type' => 'SongFileFolder',
                    'id' => $stems->id,
                    'band_space_id' => $bandSpace->id,
                    'song_id' => $song->id,
                    'parent_id' => null,
                    'name' => 'Stems',
                    'creation_datetime' => '2026-11-20T10:00:00+00:00',
                ],
            ],
        ]);
    }

    public function test_the_space_lists_every_song_folder_and_none_of_another_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $otherSong = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $demos = SongFileFolderFactory::createOne(['song' => $otherSong, 'createdBy' => $user, 'name' => 'Démos']);
        SongFileFolderFactory::createOne(['song' => SongFactory::new(['bandSpace' => BandSpaceFactory::new()]), 'createdBy' => $user, 'name' => 'Ailleurs']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/song_file_folders', [], self::JSON);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongFileFolder',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/song_file_folders',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $otherSong->id . '/file_folders/' . $demos->id,
                    '@type' => 'SongFileFolder',
                    'id' => $demos->id,
                    'band_space_id' => $bandSpace->id,
                    'song_id' => $otherSong->id,
                    'parent_id' => null,
                    'name' => 'Démos',
                    'creation_datetime' => '2026-11-20T10:00:00+00:00',
                ],
                [
                    '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id,
                    '@type' => 'SongFileFolder',
                    'id' => $stems->id,
                    'band_space_id' => $bandSpace->id,
                    'song_id' => $song->id,
                    'parent_id' => null,
                    'name' => 'Stems',
                    'creation_datetime' => '2026-11-20T10:00:00+00:00',
                ],
            ],
        ]);
    }

    public function test_one_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id, [], self::JSON);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongFileFolder',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id,
            '@type' => 'SongFileFolder',
            'id' => $stems->id,
            'band_space_id' => $bandSpace->id,
            'song_id' => $song->id,
            'parent_id' => null,
            'name' => 'Stems',
            'creation_datetime' => '2026-11-20T10:00:00+00:00',
        ]);
    }

    public function test_a_folder_of_another_song_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $elsewhere = SongFileFolderFactory::createOne(['song' => SongFactory::new(['bandSpace' => $bandSpace]), 'createdBy' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $elsewhere->id, [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Dossier introuvable dans ce titre',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Dossier introuvable dans ce titre',
        ]);
    }

    public function test_an_unknown_song_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/songs/00000000-0000-0000-0000-000000000000/file_folders', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Titre introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Titre introuvable',
        ]);
    }

    public function test_a_non_member_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/song_file_folders', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Tu n'es pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Tu n'es pas membre de ce Band Space",
        ]);
    }
}

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
class SongFileFolderUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array MERGE_PATCH = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_rename_a_folder(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $drums = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Batterie', 'parent' => $stems]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $drums->id,
            ['name' => ' Drums '],
            self::MERGE_PATCH,
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongFileFolder',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $drums->id,
            '@type' => 'SongFileFolder',
            'id' => $drums->id,
            'band_space_id' => $bandSpace->id,
            'song_id' => $song->id,
            'parent_id' => $stems->id,
            'name' => 'Drums',
            'creation_datetime' => '2026-11-20T10:00:00+00:00',
        ]);
    }

    public function test_a_patch_that_leaves_the_name_out_keeps_it(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id,
            [],
            self::MERGE_PATCH,
        );

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

    public function test_a_sibling_name_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $demos = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Démos']);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $demos->id,
            ['name' => 'STEMS'],
            self::MERGE_PATCH,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Un dossier avec ce nom existe déjà à cet emplacement',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Un dossier avec ce nom existe déjà à cet emplacement',
        ]);
    }

    public function test_a_blank_name_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id,
            ['name' => ''],
            self::MERGE_PATCH,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'name',
                    'message' => 'Indique un nom de dossier',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'name: Indique un nom de dossier',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'name: Indique un nom de dossier',
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
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $elsewhere->id,
            ['name' => 'Stems'],
            self::MERGE_PATCH,
        );

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

    public function test_a_non_member_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $member, 'name' => 'Stems']);

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id,
            ['name' => 'Pistes'],
            self::MERGE_PATCH,
        );

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

    public function test_an_archived_song_is_read_only(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'archiveDatetime' => new \DateTimeImmutable('2026-11-01')])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id,
            ['name' => 'Pistes'],
            self::MERGE_PATCH,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce titre est archivé, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ce titre est archivé, les modifications sont désactivées',
        ]);
    }
}

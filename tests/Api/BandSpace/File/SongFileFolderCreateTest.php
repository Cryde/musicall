<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\SongFileFolder;
use App\Repository\BandSpace\SongFileFolderRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\SongFileFolderFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SongFileFolderCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_create_a_folder_at_the_song_root(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'New 45'])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', ['name' => '  Stems  '], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $folder = self::getContainer()->get(SongFileFolderRepository::class)->findOneBy(['song' => $song->id]);
        $this->assertInstanceOf(SongFileFolder::class, $folder);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongFileFolder',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $folder->id,
            '@type' => 'SongFileFolder',
            'id' => $folder->id,
            'band_space_id' => $bandSpace->id,
            'song_id' => $song->id,
            'parent_id' => null,
            'name' => 'Stems',
            'creation_datetime' => $folder->creationDatetime->format('c'),
        ]);
    }

    public function test_creating_a_folder_signals_the_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', ['name' => 'Stems'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $signals = array_values(array_filter($hub->updates, static fn (Update $update): bool => str_contains($update->getData(), '"band_space_changed"')));
        $this->assertCount(1, $signals);
        $this->assertSame(
            json_encode(['type' => 'band_space_changed', 'band_space_id' => (string) $bandSpace->id, 'module' => 'file'], JSON_THROW_ON_ERROR),
            $signals[0]->getData(),
        );
    }

    public function test_create_a_folder_on_the_third_level(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $drums = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Batterie', 'parent' => $stems]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders',
            ['name' => 'Micros', 'parentId' => $drums->id],
            self::JSON,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $folder = self::getContainer()->get(SongFileFolderRepository::class)->findOneBy(['name' => 'Micros']);
        $this->assertInstanceOf(SongFileFolder::class, $folder);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongFileFolder',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $folder->id,
            '@type' => 'SongFileFolder',
            'id' => $folder->id,
            'band_space_id' => $bandSpace->id,
            'song_id' => $song->id,
            'parent_id' => $drums->id,
            'name' => 'Micros',
            'creation_datetime' => $folder->creationDatetime->format('c'),
        ]);
    }

    public function test_a_fourth_level_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $drums = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Batterie', 'parent' => $stems]);
        $mics = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Micros', 'parent' => $drums]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders',
            ['name' => 'Overheads', 'parentId' => $mics->id],
            self::JSON,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Pas plus de 3 niveaux de dossiers dans un titre',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Pas plus de 3 niveaux de dossiers dans un titre',
        ]);
    }

    public function test_a_sibling_with_the_same_name_in_another_case_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', ['name' => 'stems'], self::JSON);

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

    public function test_the_same_name_is_fine_in_another_song(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $otherSong = SongFactory::new(['bandSpace' => $bandSpace])->create();
        SongFileFolderFactory::createOne(['song' => $otherSong, 'createdBy' => $user, 'name' => 'Stems']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', ['name' => 'Stems'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $folder = self::getContainer()->get(SongFileFolderRepository::class)->findOneBy(['song' => $song->id]);
        $this->assertInstanceOf(SongFileFolder::class, $folder);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SongFileFolder',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $folder->id,
            '@type' => 'SongFileFolder',
            'id' => $folder->id,
            'band_space_id' => $bandSpace->id,
            'song_id' => $song->id,
            'parent_id' => null,
            'name' => 'Stems',
            'creation_datetime' => $folder->creationDatetime->format('c'),
        ]);
    }

    public function test_a_blank_name_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', ['name' => '   '], self::JSON);

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

    public function test_a_parent_from_another_song_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $otherSongFolder = SongFileFolderFactory::createOne(['song' => SongFactory::new(['bandSpace' => $bandSpace]), 'createdBy' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders',
            ['name' => 'Batterie', 'parentId' => $otherSongFolder->id],
            self::JSON,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce dossier n\'appartient pas à ce titre',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'Ce dossier n\'appartient pas à ce titre',
        ]);
    }

    public function test_an_archived_song_is_read_only(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'archiveDatetime' => new \DateTimeImmutable('2026-11-01')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', ['name' => 'Stems'], self::JSON);

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

    public function test_a_song_of_another_band_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $elsewhere = SongFactory::new(['bandSpace' => BandSpaceFactory::new()])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $elsewhere->id . '/file_folders', ['name' => 'Stems'], self::JSON);

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
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders', ['name' => 'Stems'], self::JSON);

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

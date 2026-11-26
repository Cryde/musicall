<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Tests\Factory\BandSpace\File\SongFileFolderFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SongFileMoveTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_put_a_file_in_a_folder_of_its_song_and_leave_it_where_it_is_in_files(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'New 45'])->create();
        $task = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'title' => 'Mixer les stems'])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $compo = BandSpaceFolderFactory::createOne(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Compo']);
        $file = BandSpaceFileFactory::createOne([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'basse.wav',
            'folder' => $compo,
            'creationDatetime' => new \DateTime('2026-11-20 10:00:00'),
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'song',
            'sourceId' => (string) $song->id,
            'attachedBy' => $user,
            'attachedDatetime' => new \DateTime('2026-11-20 10:00:00'),
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => (string) $task->id,
            'attachedBy' => $user,
            'attachedDatetime' => new \DateTime('2026-11-21 10:00:00'),
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '/folder',
            ['folderId' => $stems->id],
            self::JSON,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFile',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            '@type' => 'BandSpaceFile',
            'id' => $file->id,
            'band_space_id' => $bandSpace->id,
            'original_name' => 'basse.wav',
            'folder_id' => $compo->id,
            'folder_path' => [['id' => $compo->id, 'name' => 'Compo']],
            'tags' => [],
            'attachments' => [
                ['source_type' => 'song', 'source_id' => $song->id, 'source_label' => 'New 45', 'song_folder_id' => $stems->id],
                ['source_type' => 'task', 'source_id' => $task->id, 'source_label' => 'Mixer les stems'],
            ],
            'version_count' => 0,
            'created_by' => [
                'id' => $user->id,
                'username' => 'bassiste',
                'display_name' => 'bassiste',
                'profile_picture_url' => null,
            ],
            'download_url' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/download',
            'creation_datetime' => '2026-11-20T10:00:00+00:00',
        ]);
    }

    public function test_moving_a_file_signals_the_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $file = BandSpaceFileFactory::createOne(['bandSpace' => $bandSpace, 'createdBy' => $user]);
        BandSpaceFileAttachmentFactory::createOne(['bandSpaceFile' => $file, 'sourceType' => 'song', 'sourceId' => (string) $song->id, 'attachedBy' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '/folder',
            ['folderId' => $stems->id],
            self::JSON,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $signals = $this->changeSignals();
        $this->assertCount(1, $signals);
        $this->assertSame(
            json_encode(['type' => 'band_space_changed', 'band_space_id' => (string) $bandSpace->id, 'module' => 'file'], JSON_THROW_ON_ERROR),
            $signals[0]->getData(),
        );
    }

    public function test_a_null_folder_puts_the_file_back_at_the_song_root(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'New 45'])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $file = BandSpaceFileFactory::createOne([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'basse.wav',
            'creationDatetime' => new \DateTime('2026-11-20 10:00:00'),
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'song',
            'sourceId' => (string) $song->id,
            'songFolder' => $stems,
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '/folder',
            ['folderId' => null],
            self::JSON,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/BandSpaceFile',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id,
            '@type' => 'BandSpaceFile',
            'id' => $file->id,
            'band_space_id' => $bandSpace->id,
            'original_name' => 'basse.wav',
            'folder_path' => [],
            'tags' => [],
            'attachments' => [
                ['source_type' => 'song', 'source_id' => $song->id, 'source_label' => 'New 45'],
            ],
            'version_count' => 0,
            'created_by' => [
                'id' => $user->id,
                'username' => 'bassiste',
                'display_name' => 'bassiste',
                'profile_picture_url' => null,
            ],
            'download_url' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/download',
            'creation_datetime' => '2026-11-20T10:00:00+00:00',
        ]);
    }

    public function test_a_folder_of_another_song_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $elsewhere = SongFileFolderFactory::createOne(['song' => SongFactory::new(['bandSpace' => $bandSpace]), 'createdBy' => $user]);
        $file = BandSpaceFileFactory::createOne(['bandSpace' => $bandSpace, 'createdBy' => $user]);
        BandSpaceFileAttachmentFactory::createOne(['bandSpaceFile' => $file, 'sourceType' => 'song', 'sourceId' => (string) $song->id, 'attachedBy' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '/folder',
            ['folderId' => $elsewhere->id],
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

    public function test_a_file_the_song_does_not_hold_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $file = BandSpaceFileFactory::createOne(['bandSpace' => $bandSpace, 'createdBy' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '/folder',
            ['folderId' => $stems->id],
            self::JSON,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Le fichier n\'est pas attaché à ce titre',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Le fichier n\'est pas attaché à ce titre',
        ]);
    }

    public function test_an_archived_song_is_read_only(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'archiveDatetime' => new \DateTimeImmutable('2026-11-01')])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);
        $file = BandSpaceFileFactory::createOne(['bandSpace' => $bandSpace, 'createdBy' => $user]);
        BandSpaceFileAttachmentFactory::createOne(['bandSpaceFile' => $file, 'sourceType' => 'song', 'sourceId' => (string) $song->id, 'attachedBy' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '/folder',
            ['folderId' => $stems->id],
            self::JSON,
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

    public function test_a_non_member_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::createOne(['bandSpace' => $bandSpace, 'createdBy' => $member]);

        $this->client->loginUser($other);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '/folder',
            ['folderId' => null],
            self::JSON,
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

    /**
     * @return Update[] the band_space_changed updates the request published
     */
    private function changeSignals(): array
    {
        return array_values(array_filter(
            self::getContainer()->get(RecordingHub::class)->updates,
            static fn (Update $update): bool => str_contains($update->getData(), '"band_space_changed"'),
        ));
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\SongFileFolderRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\SongFileFolderFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Update;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SongFileFolderDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_deleting_a_folder_sends_its_files_and_its_sub_folders_files_back_to_the_song_root(): void
    {
        // Any member, not only the folder's creator: nothing is lost.
        $creator = UserFactory::new()->asBaseUser()->create(['username' => 'creator', 'email' => 'creator@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $creator])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $creator, 'name' => 'Stems']);
        $drums = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $creator, 'name' => 'Batterie', 'parent' => $stems]);
        $demos = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $creator, 'name' => 'Démos']);

        $bass = $this->songFile($bandSpace, $creator, $song, 'basse.wav', $stems);
        $kick = $this->songFile($bandSpace, $creator, $song, 'kick.wav', $drums);
        $demo = $this->songFile($bandSpace, $creator, $song, 'demo.mp3', $demos);
        $stemsId = $stems->id;
        $drumsId = $drums->id;

        $this->client->loginUser($member);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stemsId, [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCount(1, $this->changeSignals());
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $folders = self::getContainer()->get(SongFileFolderRepository::class);
        $this->assertNull($folders->find($stemsId));
        $this->assertNull($folders->find($drumsId));
        $this->assertNotNull($folders->find($demos->id));

        $this->assertSame(
            ['basse.wav' => null, 'kick.wav' => null, 'demo.mp3' => (string) $demos->id],
            [
                'basse.wav' => $this->songFolderIdOf((string) $bass->id, (string) $song->id),
                'kick.wav' => $this->songFolderIdOf((string) $kick->id, (string) $song->id),
                'demo.mp3' => $this->songFolderIdOf((string) $demo->id, (string) $song->id),
            ],
        );
        $files = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertNull($files->find($bass->id)?->archiveDatetime);
        $this->assertNull($files->find($kick->id)?->archiveDatetime);
    }

    public function test_a_folder_of_another_song_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $elsewhere = SongFileFolderFactory::createOne(['song' => SongFactory::new(['bandSpace' => $bandSpace]), 'createdBy' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $elsewhere->id, [], self::JSON);

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

    public function test_an_archived_song_is_read_only(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'archiveDatetime' => new \DateTimeImmutable('2026-11-01')])->create();
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $user, 'name' => 'Stems']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id, [], self::JSON);

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
        $stems = SongFileFolderFactory::createOne(['song' => $song, 'createdBy' => $member, 'name' => 'Stems']);

        $this->client->loginUser($other);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/file_folders/' . $stems->id, [], self::JSON);

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

    private function songFile(object $bandSpace, object $user, object $song, string $name, object $folder): object
    {
        $file = BandSpaceFileFactory::createOne(['bandSpace' => $bandSpace, 'createdBy' => $user, 'originalName' => $name]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'song',
            'sourceId' => (string) $song->id,
            'songFolder' => $folder,
            'attachedBy' => $user,
        ]);

        return $file;
    }

    private function songFolderIdOf(string $fileId, string $songId): ?string
    {
        $file = self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId);
        $this->assertNotNull($file);
        $attachment = self::getContainer()->get(BandSpaceFileAttachmentRepository::class)->findOneByFileAndSource($file, 'song', $songId);
        $this->assertInstanceOf(BandSpaceFileAttachment::class, $attachment);

        return $attachment->songFolder?->id;
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

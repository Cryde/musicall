<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceSongFileAttachTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_attach_happy_path(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'My song'])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $files = $repo->findBy(['bandSpace' => $bandSpace->id]);
        $this->assertCount(1, $files);

        /** @var BandSpaceFile $file */
        $file = $files[0];
        $this->assertSame('sample.txt', $file->originalName);

        $attachmentRepo = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $attachment = $attachmentRepo->findOneByFileAndSource($file, 'song', (string) $song->id);
        $this->assertNotNull($attachment);
    }

    public function test_attach_song_in_other_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $otherBand = BandSpaceFactory::new()->create();
        $songInOther = SongFactory::new(['bandSpace' => $otherBand, 'title' => 'Foreign'])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $songInOther->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Chanson introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Chanson introuvable',
        ]);
    }

    public function test_attach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($other);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }

    public function test_detach_removes_attachment(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        // Seed file + attachment directly so the test makes a single API call
        // (loginUser persists for one jsonRequest only — multi-request upload
        // then delete loses auth between calls).
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'song',
            'sourceId' => Uuid::fromString((string) $song->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $attachmentRepo = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepo->findOneByFileAndSource($file, 'song', (string) $song->id));
    }

    public function test_detach_with_archive_flag_archives_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'song',
            'sourceId' => Uuid::fromString((string) $song->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id . '?archive=true'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $fileId = (string) $file->id;
        self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->clear();
        $refreshed = self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId);
        $this->assertNotNull($refreshed);
        $this->assertNotNull($refreshed->archiveDatetime, 'archive=true must set archiveDatetime');
    }

    public function test_detach_when_not_attached_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        // File exists but no attachment to this song.
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Le fichier n'est pas attaché à cette chanson",
            'status' => 404,
            'type' => '/errors/404',
            'description' => "Le fichier n'est pas attaché à cette chanson",
        ]);
    }

    public function test_detach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'song',
            'sourceId' => Uuid::fromString((string) $song->id),
            'attachedBy' => $member,
        ]);

        $this->client->loginUser($other);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/songs/' . $song->id . '/files/' . $file->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }
}

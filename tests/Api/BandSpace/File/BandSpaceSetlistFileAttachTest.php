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
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\User\UserFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceSetlistFileAttachTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_attach_happy_path(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Live 2026'])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files',
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
        $attachment = $attachmentRepo->findOneByFileAndSource($file, 'setlist', (string) $setlist->id);
        $this->assertNotNull($attachment);
    }

    public function test_attach_setlist_in_other_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $otherBand = BandSpaceFactory::new()->create();
        $setlistInOther = SetlistFactory::new(['bandSpace' => $otherBand, 'name' => 'Foreign'])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlistInOther->id . '/files',
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
            'detail' => 'Setlist introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Setlist introuvable',
        ]);
    }

    public function test_attach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($other);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files',
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
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'setlist',
            'sourceId' => Uuid::fromString((string) $setlist->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files/' . $file->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $attachmentRepo = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($attachmentRepo->findOneByFileAndSource($file, 'setlist', (string) $setlist->id));
    }

    public function test_detach_with_archive_flag_archives_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'setlist',
            'sourceId' => Uuid::fromString((string) $setlist->id),
            'attachedBy' => $user,
        ]);

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files/' . $file->id . '?archive=true'
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
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files/' . $file->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Le fichier n'est pas attaché à ce setlist",
            'status' => 404,
            'type' => '/errors/404',
            'description' => "Le fichier n'est pas attaché à ce setlist",
        ]);
    }

    public function test_detach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $member])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'setlist',
            'sourceId' => Uuid::fromString((string) $setlist->id),
            'attachedBy' => $member,
        ]);

        $this->client->loginUser($other);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/files/' . $file->id
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

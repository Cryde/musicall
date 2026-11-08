<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceNoteFileDetachTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_detach_clears_attachment_keeps_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'note',
            'sourceId' => (string) $note->id,
            'attachedBy' => $user,
        ]);

        $fileId = (string) $file->id;
        $noteId = (string) $note->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $noteId . '/files/' . $fileId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloaded = self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->archiveDatetime);
        $this->assertNull(
            self::getContainer()->get(BandSpaceFileAttachmentRepository::class)->findOneByFileAndSource($reloaded, 'note', $noteId),
        );
    }

    public function test_detach_with_archive_query_archives_file(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'note',
            'sourceId' => (string) $note->id,
            'attachedBy' => $user,
        ]);

        $fileId = (string) $file->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id . '/files/' . $fileId . '?archive=true');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNotNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId)?->archiveDatetime);
    }

    public function test_detach_with_archive_while_attached_to_a_finance_entry_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'note',
            'sourceId' => (string) $note->id,
            'attachedBy' => $user,
        ]);
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'finance',
            'sourceId' => Uuid::v7()->toRfc4122(),
            'attachedBy' => $user,
        ]);

        $fileId = (string) $file->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id . '/files/' . $fileId . '?archive=true');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Ce fichier est attaché à une entrée financière. Détache-le d'abord depuis la ressource concernée.",
            'status' => 422,
            'type' => '/errors/422',
            'description' => "Ce fichier est attaché à une entrée financière. Détache-le d'abord depuis la ressource concernée.",
        ]);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId)?->archiveDatetime);
    }
}

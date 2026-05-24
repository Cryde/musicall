<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
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
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceNoteFileAttachTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_attach_image_to_note(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Plan studio'])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.png', 'cover.png', 'image/png', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['current_version_number']);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $files = $repo->findBy(['bandSpace' => $bandSpace]);
        $this->assertCount(1, $files);

        /** @var BandSpaceFile $file */
        $file = $files[0];
        $this->assertSame('cover.png', $file->originalName);

        $attachmentRepo = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $attachment = $attachmentRepo->findOneByFileAndSource($file, 'note', $note->id);
        $this->assertNotNull($attachment);
    }

    public function test_attach_non_image_returns_415(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }

    public function test_attach_note_in_other_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $otherBand = BandSpaceFactory::new()->create();
        $foreignNote = BandSpaceNoteFactory::new(['bandSpace' => $otherBand])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.png', 'cover.png', 'image/png', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $foreignNote->id . '/files',
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
            'detail' => 'Note introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Note introuvable',
        ]);
    }

    public function test_attach_records_dual_activity_under_notes_module(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace, 'title' => 'Studio plan'])->create();
        $bandSpaceId = (string) $bandSpace->id;
        $noteId = (string) $note->id;

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.png', 'cover.png', 'image/png', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpaceId . '/notes/' . $noteId . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Re-fetch the band-space after clear() so passing it as a Doctrine
        // query parameter keeps a valid identifier.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloadedBand = self::getContainer()->get(\App\Repository\BandSpace\BandSpaceRepository::class)->find($bandSpaceId);
        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);

        // The notes feed must surface this file attachment as a dedicated row.
        $noteActivities = $activityRepo->findForResource($reloadedBand, BandSpaceModule::Notes, $noteId);
        $attached = array_values(array_filter(
            $noteActivities,
            fn ($a) => $a->type === 'note_file_attached'
        ));
        $this->assertCount(1, $attached, 'Note file attach must record a row under BandSpaceModule::Notes');
        $this->assertSame('cover.png', $attached[0]->payload['original_name'] ?? null);
        $this->assertNotEmpty($attached[0]->payload['file_id'] ?? null);
    }

    public function test_attach_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.png', 'cover.png', 'image/png', null, true);

        $this->client->loginUser($other);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/notes/' . $note->id . '/files',
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
}

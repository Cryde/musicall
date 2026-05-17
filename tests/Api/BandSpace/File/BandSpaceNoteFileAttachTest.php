<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\User\UserFactory;
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

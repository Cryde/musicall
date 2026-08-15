<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileTagFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFolderFactory;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceFileUploadTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    /** @var string[] */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function test_upload_happy_path(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $files = $repo->findBy(['bandSpace' => $bandSpace]);
        $this->assertCount(1, $files);

        /** @var BandSpaceFile $file */
        $file = $files[0];
        $this->assertSame('sample.txt', $file->originalName);
        $this->assertNotNull($file->currentVersion);
        $this->assertSame(1, $file->currentVersion->versionNumber);
        $this->assertSame('text/plain', $file->currentVersion->mimeType);
        $this->assertGreaterThan(0, $file->currentVersion->size);
    }

    public function test_upload_with_folder_and_tags(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $folder = BandSpaceFolderFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user, 'name' => 'Setlists'])->create();
        $tag1 = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'masters'])->create();
        $tag2 = BandSpaceFileTagFactory::new(['bandSpace' => $bandSpace, 'name' => 'riders'])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
            [
                'folderId' => $folder->id,
                'tagIds' => [$tag1->id, $tag2->id],
            ],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $files = $repo->findBy(['bandSpace' => $bandSpace]);
        $this->assertCount(1, $files);

        /** @var BandSpaceFile $file */
        $file = $files[0];
        $this->assertNotNull($file->folder);
        $this->assertSame($folder->id, $file->folder->id);
        $this->assertCount(2, $file->tags);
    }

    public function test_upload_disallowed_mime_returns_415(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.sh', 'sample.sh', 'application/x-sh', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/415',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Type de fichier non autorisé : text/x-shellscript',
            'status' => 415,
            'type' => '/errors/415',
            'description' => 'Type de fichier non autorisé : text/x-shellscript',
        ]);
    }

    public function test_upload_svg_is_rejected_with_415(): void
    {
        // SVG can carry inline scripts, so it is no longer an allowed band-space
        // file type (SECURITY-FIX.md finding 10).
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.svg', 'sample.svg', 'image/svg+xml', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/415',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Type de fichier non autorisé : image/svg+xml',
            'status' => 415,
            'type' => '/errors/415',
            'description' => 'Type de fichier non autorisé : image/svg+xml',
        ]);
    }

    public function test_upload_no_file_returns_422(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
            [],
            [],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'uploaded_file',
                    'message' => 'Veuillez sélectionner un fichier',
                    'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720',
                ],
            ],
            'detail' => 'uploaded_file: Veuillez sélectionner un fichier',
            'description' => 'uploaded_file: Veuillez sélectionner un fichier',
            'type' => '/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            'title' => 'An error occurred',
        ]);
    }

    public function test_upload_not_member_returns_403(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'member', 'email' => 'member@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($other);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
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

    /**
     * The other half of the contract the Files import dialog's error handling rests on.
     *
     * A batch tells a refusal that stops the whole batch (the space's quota is full, a 422 with no
     * violation) from one that only concerns the file in hand (a 422 carrying violations, which is
     * this one) by whether violations are present, and stops or carries on accordingly. Both shapes
     * therefore have to be pinned: test_upload_no_file_returns_422 covers Assert\NotNull, and this
     * covers a file that is simply too big, which is the one a member meets dragging in video.
     *
     * The message quotes PHP's upload_max_filesize rather than BandSpaceFileMimeAllowlist's 500 MiB
     * cap because the ini limit is the lower of the two and is reached first: HttpKernelBrowser and
     * PHP itself both reject the upload before Assert\File is consulted. Both routes end in the same
     * shape, which is all the dialog reads. Raising the ini limit past the cap would put the
     * constraint's own message here instead, and this assertion is what would say so.
     */
    public function test_upload_over_the_per_file_cap_returns_422_with_a_violation(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $oversized = $this->createSparseFile(BandSpaceFileMimeAllowlist::MAX_UPLOAD_SIZE_BYTES + 1);
        $upload = new UploadedFile($oversized, 'concert-4k.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $violation = sprintf(
            'Le fichier est trop volumineux. Sa taille ne doit pas dépasser %d bytes.',
            UploadedFile::getMaxFilesize(),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/1',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'uploaded_file',
                    'message' => $violation,
                    'code' => '1',
                ],
            ],
            'detail' => 'uploaded_file: ' . $violation,
            'description' => 'uploaded_file: ' . $violation,
            'type' => '/validation_errors/1',
            'title' => 'An error occurred',
        ]);

        // Nothing persisted: validation runs before the processor is ever called.
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertCount(0, $repo->findBy(['bandSpace' => $bandSpace]));
    }

    /**
     * A file that reports the given size without occupying it. Half a gigabyte of real bytes would
     * make this test unusable; the size cap is checked with filesize(), which a hole satisfies.
     */
    private function createSparseFile(int $size): string
    {
        $path = tempnam(sys_get_temp_dir(), 'm862_oversized_');
        $this->temporaryFiles[] = $path;

        $handle = fopen($path, 'w');
        ftruncate($handle, $size);
        fclose($handle);
        clearstatcache(true, $path);

        $this->assertSame($size, filesize($path), 'the filesystem did not honour the sparse file');

        return $path;
    }

    public function test_upload_returns_429_when_rate_limit_exceeded(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        // Burn the full 30/min budget for this user up front so the single
        // request below is the one that trips the limiter.
        /** @var RateLimiterFactoryInterface $uploadLimiter */
        $uploadLimiter = self::getContainer()->get('limiter.band_space_file_upload');
        $uploadLimiter->create($user->id)->consume(30);

        $upload = new UploadedFile(__DIR__ . '/fixtures/sample.txt', 'sample.txt', 'text/plain', null, true);

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/files',
            [],
            ['uploadedFile' => $upload],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);

        // No file persisted when the limiter rejects.
        $repo = self::getContainer()->get(BandSpaceFileRepository::class);
        $this->assertCount(0, $repo->findBy(['bandSpace' => $bandSpace]));
    }
}

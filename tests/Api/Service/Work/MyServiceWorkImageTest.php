<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Work;

use App\ApiResource\Service\Work\MyServiceWorkImageInput;
use App\Entity\Service\ServiceWork;
use App\Entity\Service\ServiceWorkImage;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceWorkFactory;
use App\Tests\Factory\Service\ServiceWorkImageFactory;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The images of a work (#1407): added, ordered and removed by the owner, streamed to whoever reads the page. */
#[ResetDatabase]
class MyServiceWorkImageTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array MULTIPART = ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'];

    /** @var list<string> */
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

    /** Converted like a wall photo: WebP, 1600 px at most, and the EXIF, a photo's GPS position with it, gone. */
    public function test_its_owner_adds_an_image(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        ServiceWorkImageFactory::new()->create(['work' => $work, 'position' => 3]);
        $upload = $this->jpegWithExif(3200, 2000, 'IMG_2041.jpg');
        $this->assertStringContainsString('Exif', (string) file_get_contents($upload->getPathname()));

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images', [], ['file' => $upload], self::MULTIPART);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $image = $this->newestImageOf($work);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyServiceWorkImage',
            '@id' => '/api/user/services/' . $service->id . '/works/' . $work->id . '/images/' . $image->id,
            '@type' => 'MyServiceWorkImage',
            'id' => $image->id,
            'url' => '/api/services/' . $service->id . '/works/' . $work->id . '/images/' . $image->id,
        ]);
        $this->assertSame(4, $image->position);
        $this->assertSame('IMG_2041.webp', $image->originalName);
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $stored = $filesystem->read('/service_work_images/' . $service->id . '/' . $image->storagePath);
        $this->assertSame([1600, 1000, IMAGETYPE_WEBP], array_slice((array) getimagesizefromstring($stored), 0, 3));
        $this->assertStringNotContainsString('Exif', $stored);
    }

    /** @return iterable<string, array{string, string}> */
    public static function otherAcceptedFormats(): iterable
    {
        yield 'png' => ['image/png', 'cover.png'];
        yield 'webp' => ['image/webp', 'cover.webp'];
    }

    #[DataProvider('otherAcceptedFormats')]
    public function test_a_png_and_a_webp_are_accepted(string $mimeType, string $clientName): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        $path = $this->temporaryPath();
        $mimeType === 'image/png' ? imagepng(imagecreatetruecolor(100, 100), $path) : imagewebp(imagecreatetruecolor(100, 100), $path);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images', [], [
            'file' => new UploadedFile($path, $clientName, $mimeType, null, true),
        ], self::MULTIPART);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $image = $this->newestImageOf($work);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyServiceWorkImage',
            '@id' => '/api/user/services/' . $service->id . '/works/' . $work->id . '/images/' . $image->id,
            '@type' => 'MyServiceWorkImage',
            'id' => $image->id,
            'url' => '/api/services/' . $service->id . '/works/' . $work->id . '/images/' . $image->id,
        ]);
        $this->assertSame('image/webp', $image->mimeType);
    }

    /**
     * Through the validator, not a request: the test container keeps PHP's 2 MB upload_max_filesize, which refuses a
     * bigger upload before this limit is reached. The web server allows 512 MB.
     */
    public function test_an_image_over_10_mb_is_refused(): void
    {
        $upload = $this->jpegWithExif(100, 100, 'IMG_HUGE.jpg');
        // Bytes after the image's end marker: still a readable JPEG, just heavier than the limit.
        file_put_contents($upload->getPathname(), str_repeat("\0", 10_000_001), FILE_APPEND);
        $input = new MyServiceWorkImageInput();
        $input->file = new File($upload->getPathname());

        $violations = self::getContainer()->get('validator')->validate($input);

        $this->assertSame(
            [['file', "L'image dépasse 10 Mo", 'df8637af-d466-48c6-a59d-e7126250a654']],
            array_map(static fn (ConstraintViolationInterface $violation): array => [$violation->getPropertyPath(), $violation->getMessage(), $violation->getCode()], iterator_to_array($violations)),
        );
    }

    public function test_an_upload_without_a_file_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images', [], [], self::MULTIPART);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'file', 'message' => 'Choisis une image', 'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720'],
            ],
            'detail' => 'file: Choisis une image',
            'type' => '/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            'title' => 'An error occurred',
            'description' => 'file: Choisis une image',
        ]);
    }

    public function test_the_rate_limit(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        self::getContainer()->get('limiter.service_work_image_upload')->create((string) $user->id)->consume(60);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images', [], ['file' => $this->jpegWithExif(100, 100, 'IMG.jpg')], self::MULTIPART);

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
        $this->assertSame(0, $work->images->count());
    }

    public function test_an_image_past_the_limit_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        ServiceWorkImageFactory::new()->many(ServiceWorkImage::MAX_PER_WORK)->create(['work' => $work]);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images', [], ['file' => $this->jpegWithExif(100, 100, 'IMG.jpg')], self::MULTIPART);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette réalisation a déjà 12 images',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Cette réalisation a déjà 12 images',
        ]);
    }

    /** JPEG, PNG and WebP only: a GIF would keep its frames as they came, and its metadata with them. */
    public function test_a_gif_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        $path = $this->temporaryPath();
        imagegif(imagecreatetruecolor(100, 100), $path);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images', [], [
            'file' => new UploadedFile($path, 'cover.gif', 'image/gif', null, true),
        ], self::MULTIPART);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'file', 'message' => 'Envoie une image JPEG, PNG ou WebP', 'code' => '744f00bc-4389-4c74-92de-9a43cde55534'],
            ],
            'detail' => 'file: Envoie une image JPEG, PNG ou WebP',
            'type' => '/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            'title' => 'An error occurred',
            'description' => 'file: Envoie une image JPEG, PNG ou WebP',
        ]);
        $this->assertSame(0, $work->images->count());
    }

    public function test_another_members_work_takes_no_image(): void
    {
        $work = ServiceWorkFactory::new()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('POST', '/api/user/services/' . $work->service->id . '/works/' . $work->id . '/images', [], ['file' => $this->jpegWithExif(100, 100, 'IMG.jpg')], self::MULTIPART);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Service introuvable',
            'description' => 'Service introuvable',
        ]);
    }

    public function test_its_owner_deletes_an_image(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        $image = $this->storedImage($work);
        $imageId = $image->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images/' . $imageId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull(self::getContainer()->get('doctrine')->getManager()->find(ServiceWorkImage::class, $imageId));
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $this->assertFalse($filesystem->fileExists('/service_work_images/' . $service->id . '/cover.webp'));
    }

    /** An image is found only under its own work, even among the owner's. */
    public function test_an_image_of_another_work_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        $other = ServiceWorkImageFactory::new()->create(['work' => ServiceWorkFactory::new()->create(['service' => $service])]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images/' . $other->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Image introuvable',
            'description' => 'Image introuvable',
        ]);
        $this->assertNotNull(self::getContainer()->get('doctrine')->getManager()->find(ServiceWorkImage::class, $other->id));
    }

    public function test_its_owner_reorders_the_images(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        $first = ServiceWorkImageFactory::new()->create(['work' => $work, 'originalName' => 'first.webp', 'position' => 0]);
        $second = ServiceWorkImageFactory::new()->create(['work' => $work, 'originalName' => 'second.webp', 'position' => 1]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images/reorder', [
            'ids' => [$second->id, $first->id],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $rows = self::getContainer()->get('doctrine')->getConnection()->fetchAllAssociative('SELECT original_name FROM service_work_image ORDER BY position');
        $this->assertSame(['second.webp', 'first.webp'], array_column($rows, 'original_name'));
    }

    public function test_an_image_order_that_misses_one_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        $first = ServiceWorkImageFactory::new()->create(['work' => $work, 'position' => 0]);
        ServiceWorkImageFactory::new()->create(['work' => $work, 'position' => 1]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works/' . $work->id . '/images/reorder', [
            'ids' => [$first->id],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Envoie chaque élément une fois, dans le nouvel ordre',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Envoie chaque élément une fois, dans le nouvel ordre',
        ]);
    }

    public function test_another_member_cannot_reorder_the_images(): void
    {
        $work = ServiceWorkFactory::new()->create();
        $image = ServiceWorkImageFactory::new()->create(['work' => $work]);

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->jsonRequest('POST', '/api/user/services/' . $work->service->id . '/works/' . $work->id . '/images/reorder', [
            'ids' => [$image->id],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Service introuvable',
            'description' => 'Service introuvable',
        ]);
    }

    public function test_an_image_streams_to_a_visitor(): void
    {
        $this->client->disableReboot();
        $work = ServiceWorkFactory::new()->create();
        $image = $this->storedImage($work);

        $this->client->request('GET', '/api/services/' . $work->service->id . '/works/' . $work->id . '/images/' . $image->id);

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('webp-bytes', $this->client->getInternalResponse()->getContent());
    }

    /** A draft is its owner's alone (#1406), its images too. */
    public function test_an_image_of_a_draft_is_not_served_to_a_visitor(): void
    {
        $this->client->disableReboot();
        $work = ServiceWorkFactory::new()->create(['service' => ServiceFactory::new()->soundEngineering()->create(['status' => ServiceStatus::Draft])]);
        $image = $this->storedImage($work);

        $this->client->request('GET', '/api/services/' . $work->service->id . '/works/' . $work->id . '/images/' . $image->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Service introuvable',
            'description' => 'Service introuvable',
        ]);
    }

    public function test_its_owner_sees_the_images_of_their_draft(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $work = ServiceWorkFactory::new()->create(['service' => ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'status' => ServiceStatus::Draft])]);
        $image = $this->storedImage($work);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/services/' . $work->service->id . '/works/' . $work->id . '/images/' . $image->id);

        $this->assertResponseIsSuccessful();
        $this->assertSame('webp-bytes', $this->client->getInternalResponse()->getContent());
    }

    /** An image is streamed only under its own work. */
    public function test_an_image_is_not_served_under_another_work(): void
    {
        $this->client->disableReboot();
        $work = ServiceWorkFactory::new()->create();
        $image = $this->storedImage($work);
        $other = ServiceWorkFactory::new()->create(['service' => $work->service]);

        $this->client->request('GET', '/api/services/' . $work->service->id . '/works/' . $other->id . '/images/' . $image->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Image introuvable',
            'description' => 'Image introuvable',
        ]);
    }

    private function storedImage(ServiceWork $work): ServiceWorkImage
    {
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $filesystem->write('/service_work_images/' . $work->service->id . '/cover.webp', 'webp-bytes');

        return ServiceWorkImageFactory::new()->create(['work' => $work, 'storagePath' => 'cover.webp']);
    }

    private function newestImageOf(ServiceWork $work): ServiceWorkImage
    {
        $image = self::getContainer()->get('doctrine')->getManager()->getRepository(ServiceWorkImage::class)->findOneBy(['work' => $work->id], ['position' => 'DESC']);
        $this->assertInstanceOf(ServiceWorkImage::class, $image);

        return $image;
    }

    /** A JPEG with an EXIF block, as a phone writes one: the stored copy must not keep it. */
    private function jpegWithExif(int $width, int $height, string $clientName): UploadedFile
    {
        $path = $this->temporaryPath();
        imagejpeg(imagecreatetruecolor($width, $height), $path);
        // An empty big-endian TIFF directory: a valid EXIF block that says nothing, so no orientation is applied.
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00";
        $exif = "Exif\x00\x00" . $tiff;
        $jpeg = (string) file_get_contents($path);
        file_put_contents($path, substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));

        return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
    }

    private function temporaryPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'service_work_image_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}

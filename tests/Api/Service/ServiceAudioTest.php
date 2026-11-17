<?php

declare(strict_types=1);

namespace App\Tests\Api\Service;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceAudio;
use App\Enum\Service\BeforeAfterSide;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** « Avant, après » (#1410): the owner uploads one sound per side, anyone who may read the page hears it. */
#[ResetDatabase]
class ServiceAudioTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string STORED_BYTES = 'stored-sound';

    /** @var string[] */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_its_owner_uploads_a_side_converted_to_stereo_aac(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->upload($service->id, 'after', $this->wav(), ['title' => ' Mixé et masterisé ', 'credit' => 'Mona Lisa Overdrive, « Satellites »']);

        $this->assertResponseIsSuccessful();
        $audio = $this->storedAudio($service->id, BeforeAfterSide::After);
        // Checked for shape rather than pinned: the exact values depend on the ffmpeg build's AAC encoder.
        $this->assertCount(48, $audio->peaks);
        $this->assertSame(255, max($audio->peaks));
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyServiceAudio',
            '@id' => '/api/user/services/' . $service->id . '/before-after/after',
            '@type' => 'MyServiceAudio',
            'id' => $service->id,
            'audio_id' => $audio->id,
            'side' => 'after',
            'title' => 'Mixé et masterisé',
            'credit' => 'Mona Lisa Overdrive, « Satellites »',
            'duration_seconds' => 2,
            'peaks' => $audio->peaks,
        ]);
        $stored = $this->filesystem()->read($this->storedPath($service->id, $audio));
        $this->assertSame($audio->size, strlen($stored));
        $this->assertSame(['codec_name=aac', 'channels=2'], $this->probe($stored));
    }

    public function test_a_new_upload_replaces_the_side_and_its_file(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $old = $this->seedAudio($service, BeforeAfterSide::Before);
        $oldPath = $this->storedPath($service->id, $old);

        $this->client->loginUser($user);
        $this->upload($service->id, 'before', $this->wav());

        $this->assertResponseIsSuccessful();
        $this->assertFalse($this->filesystem()->fileExists($oldPath));
        $audio = $this->storedAudio($service->id, BeforeAfterSide::Before);
        $this->assertNotSame($old->id, $audio->id);
        $this->assertNull($audio->title);
        $this->assertCount(1, $this->refreshed($service->id)->audio);
    }

    public function test_a_file_that_is_not_audio_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $path = $this->temporaryPath();
        file_put_contents($path, '%PDF-1.4 not a sound');

        $this->client->loginUser($user);
        $this->upload($service->id, 'after', new UploadedFile($path, 'mix.wav', 'audio/wav', null, true));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'file', 'message' => 'Envoie un fichier WAV, AIFF, FLAC, MP3 ou M4A', 'code' => '744f00bc-4389-4c74-92de-9a43cde55534'],
            ],
            'detail' => 'file: Envoie un fichier WAV, AIFF, FLAC, MP3 ou M4A',
            'type' => '/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            'title' => 'An error occurred',
            'description' => 'file: Envoie un fichier WAV, AIFF, FLAC, MP3 ou M4A',
        ]);
    }

    /** A WAV header with nothing playable behind it: ffmpeg refuses it, and what was stored stays. */
    public function test_an_unreadable_sound_is_refused_and_the_stored_one_kept(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $old = $this->seedAudio($service, BeforeAfterSide::Before);
        $path = $this->temporaryPath();
        file_put_contents($path, 'RIFF' . pack('V', 36) . 'WAVEfmt ' . str_repeat("\xff", 40));

        $this->client->loginUser($user);
        $this->upload($service->id, 'before', new UploadedFile($path, 'take.wav', 'audio/wav', null, true));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce fichier audio ne peut pas être lu',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Ce fichier audio ne peut pas être lu',
        ]);
        $this->assertSame($old->id, $this->storedAudio($service->id, BeforeAfterSide::Before)->id);
    }

    public function test_a_post_without_a_file_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/user/services/' . $service->id . '/before-after/after', ['title' => 'Mix'], [], ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'file', 'message' => 'Choisis un fichier audio', 'code' => 'ad32d13f-c3d4-423b-909a-857b961eb720'],
            ],
            'detail' => 'file: Choisis un fichier audio',
            'type' => '/validation_errors/ad32d13f-c3d4-423b-909a-857b961eb720',
            'title' => 'An error occurred',
            'description' => 'file: Choisis un fichier audio',
        ]);
    }

    public function test_a_visitor_cannot_upload(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->upload($service->id, 'after', $this->wav());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /** What the owner wanted is already true. */
    public function test_deleting_an_empty_side_is_a_204(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/before-after/before');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
    }

    public function test_deleting_a_side_deletes_its_file(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $audio = $this->seedAudio($service, BeforeAfterSide::After);
        $path = $this->storedPath($service->id, $audio);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/before-after/after');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertCount(0, $this->refreshed($service->id)->audio);
        $this->assertFalse($this->filesystem()->fileExists($path));
    }

    /** Only a kind with « Avant, après » takes a sound. */
    public function test_a_teaching_service_takes_no_sound(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->upload($service->id, 'after', $this->wav());

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

    public function test_another_members_service_takes_no_sound(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/before-after/after');

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

    public function test_a_visitor_hears_a_published_sound(): void
    {
        $this->client->disableReboot();
        $service = ServiceFactory::new()->soundEngineering()->create();
        $this->seedAudio($service, BeforeAfterSide::After);

        $this->client->request('GET', '/api/services/' . $service->id . '/before-after/after');

        $this->assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        $this->assertSame('audio/mp4', $headers->get('Content-Type'));
        $this->assertSame('inline; filename=after.m4a', $headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertSame('max-age=300, private', $headers->get('Cache-Control'));
        $this->assertSame(self::STORED_BYTES, $this->client->getInternalResponse()->getContent());
    }

    public function test_a_drafts_sound_is_not_found_for_a_visitor(): void
    {
        $this->client->disableReboot();
        $service = ServiceFactory::new()->soundEngineering()->create(['status' => ServiceStatus::Draft]);
        $this->seedAudio($service, BeforeAfterSide::After);

        $this->client->request('GET', '/api/services/' . $service->id . '/before-after/after');

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

    public function test_its_owner_hears_their_drafts_sound(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user, 'status' => ServiceStatus::Draft]);
        $this->seedAudio($service, BeforeAfterSide::Before);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/services/' . $service->id . '/before-after/before');

        $this->assertResponseIsSuccessful();
        $this->assertSame(self::STORED_BYTES, $this->client->getInternalResponse()->getContent());
    }

    public function test_a_side_with_no_sound_is_not_found(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->request('GET', '/api/services/' . $service->id . '/before-after/before');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Ce son est introuvable',
            'description' => 'Ce son est introuvable',
        ]);
    }

    /** @param array<string, string> $fields */
    private function upload(string $serviceId, string $side, UploadedFile $file, array $fields = []): void
    {
        $this->client->request(
            'POST',
            '/api/user/services/' . $serviceId . '/before-after/' . $side,
            $fields,
            ['file' => $file],
            ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    /** Two seconds of a stereo tone, as a 24-bit WAV out of a studio would be. */
    private function wav(): UploadedFile
    {
        $path = $this->temporaryPath() . '.wav';
        $this->temporaryFiles[] = $path;
        (new Process(['ffmpeg', '-nostdin', '-loglevel', 'error', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2', '-ac', '2', '-c:a', 'pcm_s24le', '-y', $path]))->mustRun();

        return new UploadedFile($path, 'mix.wav', 'audio/wav', null, true);
    }

    private function seedAudio(Service $service, BeforeAfterSide $side): ServiceAudio
    {
        $audio = new ServiceAudio();
        $audio->service = $service;
        $audio->side = $side;
        $audio->durationSeconds = 2;
        $audio->peaks = [0, 255];
        $audio->size = strlen(self::STORED_BYTES);
        $audio->storagePath = 'seeded-' . $side->value . '.m4a';
        $service->audio->add($audio);
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($audio);
        $entityManager->flush();
        $this->filesystem()->write($this->storedPath((string) $service->id, $audio), self::STORED_BYTES);

        return $audio;
    }

    private function storedAudio(string $serviceId, BeforeAfterSide $side): ServiceAudio
    {
        $audio = $this->refreshed($serviceId)->audioOn($side);
        $this->assertInstanceOf(ServiceAudio::class, $audio);

        return $audio;
    }

    private function refreshed(string $serviceId): Service
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();
        $service = $entityManager->find(Service::class, $serviceId);
        $this->assertInstanceOf(Service::class, $service);

        return $service;
    }

    private function storedPath(string $serviceId, ServiceAudio $audio): string
    {
        return '/service_audio/' . $serviceId . '/' . $audio->storagePath;
    }

    private function filesystem(): FilesystemOperator
    {
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');

        return $filesystem;
    }

    /** @return list<string> */
    private function probe(string $bytes): array
    {
        $path = $this->temporaryPath();
        file_put_contents($path, $bytes);
        $process = new Process(['ffprobe', '-v', 'error', '-select_streams', 'a:0', '-show_entries', 'stream=codec_name,channels', '-of', 'default=noprint_wrappers=1', $path]);
        $process->mustRun();

        return array_values(array_filter(explode("\n", trim($process->getOutput()))));
    }

    private function temporaryPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'service_audio_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}

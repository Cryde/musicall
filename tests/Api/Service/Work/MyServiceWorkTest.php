<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Work;

use App\Entity\Service\ServiceWork;
use App\Enum\Musician\MediaPlatform;
use App\Repository\Service\ServiceWorkRepository;
use App\Service\Musician\MediaMetadataFetcher;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceWorkFactory;
use App\Tests\Factory\Service\ServiceWorkImageFactory;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The « Réalisations » of a member's own service (#1407). */
#[ResetDatabase]
class MyServiceWorkTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON_LD = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];
    private const array MERGE_PATCH = ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    protected function setUp(): void
    {
        parent::setUp();
        // No fetcher: the platform is never called, so a recognised link has no title.
        self::getContainer()->set(MediaMetadataFetcher::class, new MediaMetadataFetcher([]));
    }

    public function test_its_owner_adds_a_work(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Older', 'position' => 4]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works', [
            'title' => '  Satellites  ',
            'credit' => 'Mona Lisa Overdrive, EP',
            'year' => 2024,
            'link_url' => 'https://monalisaoverdrive.bandcamp.com/album/satellites',
            'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], self::JSON_LD);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $work = $this->workTitled('Satellites');
        $this->assertSame(5, $work->position);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyServiceWork',
            '@id' => '/api/user/services/' . $service->id . '/works/' . $work->id,
            '@type' => 'MyServiceWork',
            'id' => $work->id,
            'title' => 'Satellites',
            'credit' => 'Mona Lisa Overdrive, EP',
            'year' => 2024,
            'link_url' => 'https://monalisaoverdrive.bandcamp.com/album/satellites',
            'media' => [
                '@type' => 'ServiceWorkMedia',
                'platform' => 'youtube',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'embed_id' => 'dQw4w9WgXcQ',
                'title' => null,
            ],
            'images' => [],
        ]);
    }

    public function test_a_work_needs_a_title_a_web_link_and_a_real_year(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works', [
            'title' => '   ',
            'year' => 1850,
            'link_url' => 'javascript:alert(1)',
        ], self::JSON_LD);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=04b91c99-a946-4221-afc5-e65ebac401eb;2=57c2f299-1154-4870-89bb-ef3b1f5ad229',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'Indique un titre', 'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3'],
                ['propertyPath' => 'year', 'message' => 'Indique une année entre 1900 et 2100', 'code' => '04b91c99-a946-4221-afc5-e65ebac401eb'],
                ['propertyPath' => 'link_url', 'message' => 'Indique un lien qui commence par https:// ou http://', 'code' => '57c2f299-1154-4870-89bb-ef3b1f5ad229'],
            ],
            'detail' => "title: Indique un titre\nyear: Indique une année entre 1900 et 2100\nlink_url: Indique un lien qui commence par https:// ou http://",
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=04b91c99-a946-4221-afc5-e65ebac401eb;2=57c2f299-1154-4870-89bb-ef3b1f5ad229',
            'title' => 'An error occurred',
            'description' => "title: Indique un titre\nyear: Indique une année entre 1900 et 2100\nlink_url: Indique un lien qui commence par https:// ou http://",
        ]);
        $this->assertSame(0, self::getContainer()->get(ServiceWorkRepository::class)->count([]));
    }

    public function test_a_work_past_the_limit_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        ServiceWorkFactory::new()->many(ServiceWork::MAX_PER_SERVICE)->create(['service' => $service]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works', ['title' => 'Satellites'], self::JSON_LD);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu as déjà ajouté 30 réalisations',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Tu as déjà ajouté 30 réalisations',
        ]);
    }

    /** No « Réalisations » for teachers: the lessons page has its media and « Morceaux travaillés » (#1407). */
    public function test_a_teaching_service_has_no_works(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works', ['title' => 'Satellites'], self::JSON_LD);

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

    public function test_another_members_service_takes_no_work(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works', ['title' => 'Satellites'], self::JSON_LD);

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
        $this->assertSame(0, self::getContainer()->get(ServiceWorkRepository::class)->count([]));
    }

    public function test_a_visitor_cannot_add_a_work(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works', ['title' => 'Satellites'], self::JSON_LD);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /** A merge patch (#1327): what is left out keeps its value, and the platform is not asked again for the title. */
    public function test_a_patch_that_leaves_fields_out_keeps_them(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = $this->fullWork($service);
        $image = ServiceWorkImageFactory::new()->create(['work' => $work]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/works/' . $work->id, ['title' => 'Satellites (live)'], self::MERGE_PATCH);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyServiceWork',
            '@id' => '/api/user/services/' . $service->id . '/works/' . $work->id,
            '@type' => 'MyServiceWork',
            'id' => $work->id,
            'title' => 'Satellites (live)',
            'credit' => 'Mona Lisa Overdrive, EP',
            'year' => 2024,
            'link_url' => 'https://example.com/satellites',
            'media' => [
                '@type' => 'ServiceWorkMedia',
                'platform' => 'youtube',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'embed_id' => 'dQw4w9WgXcQ',
                'title' => 'Satellites, the clip',
            ],
            'images' => [
                ['@type' => 'ServiceWorkImageView', 'id' => $image->id, 'url' => '/api/services/' . $service->id . '/works/' . $work->id . '/images/' . $image->id],
            ],
        ]);
    }

    public function test_a_patch_empties_what_it_sends_empty(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = $this->fullWork($service);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/works/' . $work->id, [
            'credit' => '',
            'year' => null,
            'link_url' => null,
            'media_url' => '',
        ], self::MERGE_PATCH);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyServiceWork',
            '@id' => '/api/user/services/' . $service->id . '/works/' . $work->id,
            '@type' => 'MyServiceWork',
            'id' => $work->id,
            'title' => 'Satellites',
            'credit' => null,
            'year' => null,
            'link_url' => null,
            'media' => null,
            'images' => [],
        ]);
    }

    /** The images go with their work, their stored files too. */
    public function test_its_owner_deletes_a_work_and_its_images(): void
    {
        $this->client->disableReboot();
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $work = ServiceWorkFactory::new()->create(['service' => $service]);
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        foreach (['front.webp', 'back.webp'] as $name) {
            $filesystem->write('/service_work_images/' . $service->id . '/' . $name, 'webp-bytes');
            ServiceWorkImageFactory::new()->create(['work' => $work, 'storagePath' => $name]);
        }
        $workId = $work->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/works/' . $workId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull(self::getContainer()->get(ServiceWorkRepository::class)->find($workId));
        $this->assertSame(0, (int) self::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT COUNT(*) FROM service_work_image'));
        $this->assertFalse($filesystem->fileExists('/service_work_images/' . $service->id . '/front.webp'));
        $this->assertFalse($filesystem->fileExists('/service_work_images/' . $service->id . '/back.webp'));
    }

    public function test_another_member_cannot_delete_a_work(): void
    {
        $work = ServiceWorkFactory::new()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('DELETE', '/api/user/services/' . $work->service->id . '/works/' . $work->id);

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
        $this->assertNotNull(self::getContainer()->get(ServiceWorkRepository::class)->find($work->id));
    }

    /** Another member's work is not found through the member's own service. */
    public function test_a_work_of_another_service_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $other = ServiceWorkFactory::new()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/services/' . $service->id . '/works/' . $other->id, ['title' => 'Mine now'], self::MERGE_PATCH);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Réalisation introuvable',
            'description' => 'Réalisation introuvable',
        ]);
        $this->assertSame('Satellites', self::getContainer()->get(ServiceWorkRepository::class)->find($other->id)?->title);
    }

    public function test_its_owner_reorders_the_works(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $first = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'First', 'position' => 0]);
        $second = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Second', 'position' => 1]);
        $third = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Third', 'position' => 2]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works/reorder', [
            'ids' => [$third->id, $first->id, $second->id],
        ], self::JSON_LD);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(['Third' => 0, 'First' => 1, 'Second' => 2], $this->positions());
    }

    /** Every work, each once: a partial order would leave positions nobody chose. */
    public function test_an_order_that_misses_a_work_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $first = ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'First', 'position' => 0]);
        ServiceWorkFactory::new()->create(['service' => $service, 'title' => 'Second', 'position' => 1]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/works/reorder', [
            'ids' => [$first->id, $first->id],
        ], self::JSON_LD);

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
        $this->assertSame(['First' => 0, 'Second' => 1], $this->positions());
    }

    private function fullWork(object $service): ServiceWork
    {
        return ServiceWorkFactory::new()->create([
            'service' => $service,
            'title' => 'Satellites',
            'credit' => 'Mona Lisa Overdrive, EP',
            'year' => 2024,
            'linkUrl' => 'https://example.com/satellites',
            'mediaPlatform' => MediaPlatform::YOUTUBE,
            'mediaUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'mediaEmbedId' => 'dQw4w9WgXcQ',
            'mediaTitle' => 'Satellites, the clip',
        ]);
    }

    private function workTitled(string $title): ServiceWork
    {
        $work = self::getContainer()->get(ServiceWorkRepository::class)->findOneBy(['title' => $title]);
        $this->assertInstanceOf(ServiceWork::class, $work);

        return $work;
    }

    /** @return array<string, int> each title's position, read from the database */
    private function positions(): array
    {
        /** @var list<array{title: string, position: int|string}> $rows */
        $rows = self::getContainer()->get('doctrine')->getConnection()->fetchAllAssociative('SELECT title, position FROM service_work ORDER BY position');

        return array_combine(array_column($rows, 'title'), array_map('intval', array_column($rows, 'position')));
    }
}

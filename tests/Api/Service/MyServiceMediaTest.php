<?php

declare(strict_types=1);

namespace App\Tests\Api\Service;

use App\Entity\Service\ServiceMedia;
use App\Repository\Service\ServiceMediaRepository;
use App\Service\Musician\MediaMetadataFetcher;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceMediaFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\Teacher\TeacherMediaLimit;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The embeds of a member's own service, by its id (#1410). */
#[ResetDatabase]
class MyServiceMediaTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        // No fetcher: the platform is never called, and the title is the one sent.
        self::getContainer()->set(MediaMetadataFetcher::class, new MediaMetadataFetcher([]));
    }

    public function test_its_owner_adds_an_embed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/media', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Jardin Public, live session',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $media = self::getContainer()->get(ServiceMediaRepository::class)->findOneBy(['service' => $service->id]);
        $this->assertInstanceOf(ServiceMedia::class, $media);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileMedia',
            '@id' => '/api/teacher_profile_media/' . $media->id,
            '@type' => 'TeacherProfileMedia',
            'id' => $media->id,
            'platform' => 'youtube',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'embed_id' => 'dQw4w9WgXcQ',
            'title' => 'Jardin Public, live session',
            'position' => 0,
        ]);
    }

    public function test_an_embed_past_the_limit_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        ServiceMediaFactory::new()->many(TeacherMediaLimit::MAX_MEDIA_PER_PROFILE)->create(['service' => $service]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/media', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu as déjà ajouté le nombre maximum de médias',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Tu as déjà ajouté le nombre maximum de médias',
        ]);
    }

    public function test_another_members_service_takes_no_embed(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/media', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
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

    public function test_a_visitor_cannot_add_an_embed(): void
    {
        $service = ServiceFactory::new()->soundEngineering()->create();

        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/media', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_its_owner_deletes_an_embed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $media = ServiceMediaFactory::new()->create(['service' => $service]);
        $mediaId = $media->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/media/' . $mediaId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull(self::getContainer()->get(ServiceMediaRepository::class)->find($mediaId));
    }

    /** An embed of another service is not found through this one, even the same member's. */
    public function test_an_embed_of_another_service_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);
        $other = ServiceMediaFactory::new()->create(['service' => ServiceFactory::new()->teaching()->create(['user' => $user])]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/services/' . $service->id . '/media/' . $other->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'status' => 404,
            'type' => '/errors/404',
            'detail' => 'Média non trouvé',
            'description' => 'Média non trouvé',
        ]);
        $this->assertNotNull(self::getContainer()->get(ServiceMediaRepository::class)->find($other->id));
    }

    /** Photographers and videographers host on Vimeo (#1411). */
    public function test_a_photography_service_takes_a_vimeo_embed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->photography()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/media', [
            'url' => 'https://vimeo.com/76979871/8272103f6e',
            'title' => 'Showreel',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $media = self::getContainer()->get(ServiceMediaRepository::class)->findOneBy(['service' => $service->id]);
        $this->assertInstanceOf(ServiceMedia::class, $media);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileMedia',
            '@id' => '/api/teacher_profile_media/' . $media->id,
            '@type' => 'TeacherProfileMedia',
            'id' => $media->id,
            'platform' => 'vimeo',
            'url' => 'https://vimeo.com/76979871/8272103f6e',
            'embed_id' => '76979871/8272103f6e',
            'title' => 'Showreel',
            'position' => 0,
        ]);
    }

    /** Vimeo for photography and video only (#1411): a sound engineer keeps the platforms every reader knows. */
    public function test_a_sound_engineering_service_refuses_a_vimeo_embed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->soundEngineering()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/media', [
            'url' => 'https://vimeo.com/76979871',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.',
                    'code' => 'music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
                ],
            ],
            'detail' => 'url: URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.',
            'description' => 'url: URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.',
            'type' => '/validation_errors/music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
            'title' => 'An error occurred',
        ]);
        $this->assertSame(0, self::getContainer()->get(ServiceMediaRepository::class)->count(['service' => $service->id]));
    }

    /** Where Vimeo is taken, the refusal names it. */
    public function test_a_video_service_refuses_a_link_no_platform_reads(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $service = ServiceFactory::new()->video()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/services/' . $service->id . '/media', [
            'url' => 'https://example.com/clip',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'URL non reconnue. Seuls YouTube, SoundCloud, Spotify et Vimeo sont supportés.',
                    'code' => 'music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
                ],
            ],
            'detail' => 'url: URL non reconnue. Seuls YouTube, SoundCloud, Spotify et Vimeo sont supportés.',
            'description' => 'url: URL non reconnue. Seuls YouTube, SoundCloud, Spotify et Vimeo sont supportés.',
            'type' => '/validation_errors/music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
            'title' => 'An error occurred',
        ]);
    }
}

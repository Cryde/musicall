<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Enum\Musician\MediaPlatform;
use App\Repository\Service\ServiceMediaRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceMediaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class TeacherProfileMediaTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_media_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create(['user' => $user]);

        // Created in reverse, so the order can only come from the position: new ids sort by creation.
        $media2 = ServiceMediaFactory::new()->create([
            'service' => $teacherProfile,
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=def456',
            'embedId' => 'def456',
            'title' => 'Second Video',
            'position' => 1,
        ]);

        $media1 = ServiceMediaFactory::new()->create([
            'service' => $teacherProfile,
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'embedId' => 'abc123',
            'title' => 'First Video',
            'position' => 0,
        ]);

        $media1Id = $media1->id;
        $media2Id = $media2->id;

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/teacher-profile/media');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileMedia',
            '@id' => '/api/user/teacher-profile/media',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/teacher_profile_media/' . $media1Id,
                    '@type' => 'TeacherProfileMedia',
                    'id' => $media1Id,
                    'platform' => 'youtube',
                    'url' => 'https://www.youtube.com/watch?v=abc123',
                    'embed_id' => 'abc123',
                    'title' => 'First Video',
                    'position' => 0,
                ],
                [
                    '@id' => '/api/teacher_profile_media/' . $media2Id,
                    '@type' => 'TeacherProfileMedia',
                    'id' => $media2Id,
                    'platform' => 'youtube',
                    'url' => 'https://www.youtube.com/watch?v=def456',
                    'embed_id' => 'def456',
                    'title' => 'Second Video',
                    'position' => 1,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_media_collection_empty(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/teacher-profile/media');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileMedia',
            '@id' => '/api/user/teacher-profile/media',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_media_collection_no_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/teacher-profile/media');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TeacherProfileMedia',
            '@id' => '/api/user/teacher-profile/media',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_media_collection_not_logged_in(): void
    {
        $this->client->request('GET', '/api/user/teacher-profile/media');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_delete_media(): void
    {
        $mediaRepository = self::getContainer()->get(ServiceMediaRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create(['user' => $user]);

        $media = ServiceMediaFactory::new()->create([
            'service' => $teacherProfile,
        ]);

        $mediaId = $media->id;
        $this->assertNotNull($mediaRepository->find($mediaId));

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/teacher-profile/media/' . $mediaId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull($mediaRepository->find($mediaId));
    }

    public function test_delete_media_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        // Use a valid UUID format that doesn't exist
        $this->client->request('DELETE', '/api/user/teacher-profile/media/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_delete_media_with_an_id_that_is_not_a_uuid(): void
    {
        // A guid column converts nothing, so a malformed id is not found rather than a 500.
        $user = UserFactory::new()->asBaseUser()->create();
        ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/teacher-profile/media/not-a-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Média non trouvé',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Média non trouvé',
        ]);
    }

    public function test_delete_media_not_owner(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create([
            'username' => 'teacher_owner',
            'email' => 'teacher_owner@test.com',
        ]);
        $user2 = UserFactory::new()->asBaseUser()->create([
            'username' => 'teacher_other',
            'email' => 'teacher_other@test.com',
        ]);

        $teacherProfile1 = ServiceFactory::new()->teaching()->create(['user' => $user1]);
        ServiceFactory::new()->teaching()->create(['user' => $user2]);

        $media = ServiceMediaFactory::new()->create([
            'service' => $teacherProfile1,
        ]);

        $mediaId = $media->id;

        // User2 tries to delete User1's media
        $this->client->loginUser($user2);
        $this->client->request('DELETE', '/api/user/teacher-profile/media/' . $mediaId);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_media_not_logged_in(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'media_owner',
            'email' => 'media_owner@test.com',
        ]);
        $teacherProfile = ServiceFactory::new()->teaching()->create(['user' => $user]);

        $media = ServiceMediaFactory::new()->create([
            'service' => $teacherProfile,
        ]);

        $mediaId = $media->id;

        $this->client->request('DELETE', '/api/user/teacher-profile/media/' . $mediaId);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_delete_media_no_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'no_profile_user',
            'email' => 'no_profile@test.com',
        ]);

        $this->client->loginUser($user);
        // Use a valid UUID format
        $this->client->request('DELETE', '/api/user/teacher-profile/media/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /** Installed app builds read a teacher's media and do not know Vimeo (#1411). */
    public function test_create_media_refuses_vimeo(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teacherProfile = ServiceFactory::new()->teaching()->create(['user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/teacher-profile/media', [
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
        $this->assertSame(0, self::getContainer()->get(ServiceMediaRepository::class)->count(['service' => $teacherProfile->id]));
    }
}

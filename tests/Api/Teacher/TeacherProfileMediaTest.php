<?php

declare(strict_types=1);

namespace App\Tests\Api\Teacher;

use App\Enum\Musician\MediaPlatform;
use App\Repository\Teacher\TeacherProfileMediaRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileMediaFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TeacherProfileMediaTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_media_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $teacherProfile = TeacherProfileFactory::new()->create(['user' => $user]);

        $media1 = TeacherProfileMediaFactory::new()->create([
            'teacherProfile' => $teacherProfile,
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=abc123',
            'embedId' => 'abc123',
            'title' => 'First Video',
            'position' => 0,
        ]);

        $media2 = TeacherProfileMediaFactory::new()->create([
            'teacherProfile' => $teacherProfile,
            'platform' => MediaPlatform::YOUTUBE,
            'url' => 'https://www.youtube.com/watch?v=def456',
            'embedId' => 'def456',
            'title' => 'Second Video',
            'position' => 1,
        ]);

        $media1Id = $media1->getId();
        $media2Id = $media2->getId();

        $this->client->loginUser($user->_real());
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
        TeacherProfileFactory::new()->create(['user' => $user]);

        $this->client->loginUser($user->_real());
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

        $this->client->loginUser($user->_real());
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
        $mediaRepository = self::getContainer()->get(TeacherProfileMediaRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $teacherProfile = TeacherProfileFactory::new()->create(['user' => $user]);

        $media = TeacherProfileMediaFactory::new()->create([
            'teacherProfile' => $teacherProfile,
        ]);

        $mediaId = $media->getId();
        $this->assertNotNull($mediaRepository->find($mediaId));

        $this->client->loginUser($user->_real());
        $this->client->request('DELETE', '/api/user/teacher-profile/media/' . $mediaId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull($mediaRepository->find($mediaId));
    }

    public function test_delete_media_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        TeacherProfileFactory::new()->create(['user' => $user]);

        $this->client->loginUser($user->_real());
        // Use a valid UUID format that doesn't exist
        $this->client->request('DELETE', '/api/user/teacher-profile/media/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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

        $teacherProfile1 = TeacherProfileFactory::new()->create(['user' => $user1]);
        TeacherProfileFactory::new()->create(['user' => $user2]);

        $media = TeacherProfileMediaFactory::new()->create([
            'teacherProfile' => $teacherProfile1,
        ]);

        $mediaId = $media->getId();

        // User2 tries to delete User1's media
        $this->client->loginUser($user2->_real());
        $this->client->request('DELETE', '/api/user/teacher-profile/media/' . $mediaId);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_media_not_logged_in(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'media_owner',
            'email' => 'media_owner@test.com',
        ]);
        $teacherProfile = TeacherProfileFactory::new()->create(['user' => $user]);

        $media = TeacherProfileMediaFactory::new()->create([
            'teacherProfile' => $teacherProfile,
        ]);

        $mediaId = $media->getId();

        $this->client->request('DELETE', '/api/user/teacher-profile/media/' . $mediaId);
        // Not logged in returns 404 because the provider can't find the user's profile
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_delete_media_no_profile(): void
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'no_profile_user',
            'email' => 'no_profile@test.com',
        ]);

        $this->client->loginUser($user->_real());
        // Use a valid UUID format
        $this->client->request('DELETE', '/api/user/teacher-profile/media/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}

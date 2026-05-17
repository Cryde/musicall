<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Course;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserCourseDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $course = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Course to delete',
            'slug' => 'course-to-delete',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->request('DELETE', '/api/user/courses/' . $course->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_delete_own_draft_course(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $course = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Draft Course',
            'slug' => 'draft-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $courseId = $course->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/courses/' . $courseId);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_cannot_delete_other_user_course(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'user1', 'email' => 'user1@test.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'user2', 'email' => 'user2@test.com']);
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $course = PublicationFactory::new()->create([
            'author' => $user1,
            'subCategory' => $category,
            'title' => 'User 1 Course',
            'slug' => 'user-1-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user2);
        $this->client->request('DELETE', '/api/user/courses/' . $course->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You are not the owner of this course',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You are not the owner of this course',
        ]);
    }

    public function test_cannot_delete_online_course(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $course = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Online Course',
            'slug' => 'online-course',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/courses/' . $course->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You can only delete draft courses',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You can only delete draft courses',
        ]);
    }

    public function test_cannot_delete_pending_course(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $course = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Pending Course',
            'slug' => 'pending-course',
            'status' => Publication::STATUS_PENDING,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/courses/' . $course->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You can only delete draft courses',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You can only delete draft courses',
        ]);
    }

    public function test_delete_not_found_course(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/courses/999999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Course not found',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Course not found',
        ]);
    }
}

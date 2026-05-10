<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Course;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class UserCourseGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('GET', '/api/user/courses');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $course1 = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Course 1',
            'slug' => 'course-1',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
            'editionDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-02T10:00:00+00:00'),
        ]);

        $course2 = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Course 2',
            'slug' => 'course-2',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-02T10:00:00+00:00'),
            'editionDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-03T10:00:00+00:00'),
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/courses?sortBy=creation_datetime&sortOrder=desc');
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('/api/contexts/UserCourse', $response['@context']);
        $this->assertSame('/api/user/courses', $response['@id']);
        $this->assertSame('Collection', $response['@type']);
        $this->assertSame(2, $response['totalItems']);
        $this->assertCount(2, $response['member']);
        $this->assertSame($course2->id, $response['member'][0]['id']);
        $this->assertSame('Course 2', $response['member'][0]['title']);
        $this->assertSame(Publication::STATUS_ONLINE, $response['member'][0]['status_id']);
        $this->assertSame($course1->id, $response['member'][1]['id']);
        $this->assertSame('Course 1', $response['member'][1]['title']);
        $this->assertSame(Publication::STATUS_DRAFT, $response['member'][1]['status_id']);
    }

    public function test_get_collection_only_own_courses(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'user1', 'email' => 'user1@test.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'user2', 'email' => 'user2@test.com']);
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $user1Course = PublicationFactory::new()->create([
            'author' => $user1,
            'subCategory' => $category,
            'title' => 'User 1 Course',
            'slug' => 'user-1-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);

        PublicationFactory::new()->create([
            'author' => $user2,
            'subCategory' => $category,
            'title' => 'User 2 Course',
            'slug' => 'user-2-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/user/courses');
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame($user1Course->id, $response['member'][0]['id']);
        $this->assertSame('User 1 Course', $response['member'][0]['title']);
    }

    public function test_get_collection_excludes_publications(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $courseCategory = PublicationSubCategoryFactory::new()->asCourseCategory()->create();
        $publicationCategory = PublicationSubCategoryFactory::new()->asNews()->create();

        $course = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $courseCategory,
            'title' => 'Course',
            'slug' => 'course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $publicationCategory,
            'title' => 'Publication',
            'slug' => 'publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/courses');
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame($course->id, $response['member'][0]['id']);
        $this->assertSame('Course', $response['member'][0]['title']);
    }

    public function test_get_collection_filter_by_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $draftCourse = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Draft Course',
            'slug' => 'draft-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Online Course',
            'slug' => 'online-course',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/courses?status=' . Publication::STATUS_DRAFT);
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame($draftCourse->id, $response['member'][0]['id']);
        $this->assertSame('Draft Course', $response['member'][0]['title']);
    }

    public function test_get_collection_filter_by_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $guitarCategory = PublicationSubCategoryFactory::new()->asCourseCategory()->create();
        $pianoCategory = PublicationSubCategoryFactory::new()->create([
            'title' => 'Piano',
            'slug' => 'piano',
            'position' => 2,
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);

        $guitarCourse = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $guitarCategory,
            'title' => 'Guitar Course',
            'slug' => 'guitar-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $pianoCategory,
            'title' => 'Piano Course',
            'slug' => 'piano-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/courses?category=' . $guitarCategory->id);
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(1, $response['totalItems']);
        $this->assertCount(1, $response['member']);
        $this->assertSame($guitarCourse->id, $response['member'][0]['id']);
        $this->assertSame('Guitar Course', $response['member'][0]['title']);
    }

    public function test_get_collection_pagination_first_page(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        for ($i = 1; $i <= 15; $i++) {
            PublicationFactory::new()->create([
                'author' => $user,
                'subCategory' => $category,
                'title' => 'Course ' . $i,
                'slug' => 'course-' . $i,
                'status' => Publication::STATUS_DRAFT,
                'type' => Publication::TYPE_TEXT,
            ]);
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/courses?itemsPerPage=10');
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(15, $response['totalItems']);
        $this->assertCount(10, $response['member']);
    }

    public function test_get_collection_pagination_second_page(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        for ($i = 1; $i <= 15; $i++) {
            PublicationFactory::new()->create([
                'author' => $user,
                'subCategory' => $category,
                'title' => 'Course ' . $i,
                'slug' => 'course-' . $i,
                'status' => Publication::STATUS_DRAFT,
                'type' => Publication::TYPE_TEXT,
            ]);
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/courses?itemsPerPage=10&page=2');
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(15, $response['totalItems']);
        $this->assertCount(5, $response['member']);
    }

    public function test_get_collection_sort_by_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asCourseCategory()->create();

        $zebraCourse = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Zebra Course',
            'slug' => 'zebra-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);

        $alphaCourse = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Alpha Course',
            'slug' => 'alpha-course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-02T10:00:00+00:00'),
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/courses?sortBy=title&sortOrder=asc');
        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(2, $response['totalItems']);
        $this->assertCount(2, $response['member']);
        $this->assertSame($alphaCourse->id, $response['member'][0]['id']);
        $this->assertSame('Alpha Course', $response['member'][0]['title']);
        $this->assertSame($zebraCourse->id, $response['member'][1]['id']);
        $this->assertSame('Zebra Course', $response['member'][1]['title']);
    }
}

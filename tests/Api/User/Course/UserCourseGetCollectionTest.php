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

class UserCourseGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/courses?sortBy=creation_datetime&sortOrder=desc');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/UserCourse',
            '@id' => '/api/user/courses',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user_courses/' . $course2->getId(),
                    '@type' => 'UserCourse',
                    'id' => $course2->getId(),
                    'title' => 'Course 2',
                    'slug' => 'course-2',
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'edition_datetime' => '2024-01-03T10:00:00+00:00',
                    'status_id' => Publication::STATUS_ONLINE,
                    'status_label' => 'Publié',
                    'type_id' => Publication::TYPE_TEXT,
                    'type_label' => 'text',
                    'category' => [
                        '@type' => 'UserCourseCategory',
                        'id' => $category->getId(),
                        'title' => 'Guitare',
                        'slug' => 'guitare',
                    ],
                ],
                [
                    '@id' => '/api/user_courses/' . $course1->getId(),
                    '@type' => 'UserCourse',
                    'id' => $course1->getId(),
                    'title' => 'Course 1',
                    'slug' => 'course-1',
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'edition_datetime' => '2024-01-02T10:00:00+00:00',
                    'status_id' => Publication::STATUS_DRAFT,
                    'status_label' => 'Brouillon',
                    'type_id' => Publication::TYPE_TEXT,
                    'type_label' => 'text',
                    'category' => [
                        '@type' => 'UserCourseCategory',
                        'id' => $category->getId(),
                        'title' => 'Guitare',
                        'slug' => 'guitare',
                    ],
                ],
            ],
            'totalItems' => 2,
        ]);
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

        $this->client->loginUser($user1->_real());
        $this->client->request('GET', '/api/user/courses');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $user1Course->getId(),
                    'title' => 'User 1 Course',
                ],
            ],
        ]);
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

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/courses');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $course->getId(),
                    'title' => 'Course',
                ],
            ],
        ]);
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

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/courses?status=' . Publication::STATUS_DRAFT);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $draftCourse->getId(),
                    'title' => 'Draft Course',
                ],
            ],
        ]);
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

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/courses?category=' . $guitarCategory->getId());
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $guitarCourse->getId(),
                    'title' => 'Guitar Course',
                ],
            ],
        ]);
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

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/courses?itemsPerPage=10');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 15,
        ]);

        $response = json_decode($this->client->getResponse()->getContent(), true);
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

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/courses?itemsPerPage=10&page=2');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 15,
        ]);

        $response = json_decode($this->client->getResponse()->getContent(), true);
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

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/courses?sortBy=title&sortOrder=asc');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 2,
            'member' => [
                [
                    'id' => $alphaCourse->getId(),
                    'title' => 'Alpha Course',
                ],
                [
                    'id' => $zebraCourse->getId(),
                    'title' => 'Zebra Course',
                ],
            ],
        ]);
    }
}

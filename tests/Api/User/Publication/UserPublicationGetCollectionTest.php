<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Publication;

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

class UserPublicationGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('GET', '/api/user/publications');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $publication1 = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Publication 1',
            'slug' => 'publication-1',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
            'editionDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-02T10:00:00+00:00'),
        ]);

        $publication2 = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Publication 2',
            'slug' => 'publication-2',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-02T10:00:00+00:00'),
            'editionDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-03T10:00:00+00:00'),
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications?sortBy=creation_datetime&sortOrder=desc');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/UserPublication',
            '@id' => '/api/user/publications',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/user_publications/' . $publication2->getId(),
                    '@type' => 'UserPublication',
                    'id' => $publication2->getId(),
                    'title' => 'Publication 2',
                    'slug' => 'publication-2',
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'edition_datetime' => '2024-01-03T10:00:00+00:00',
                    'status_id' => Publication::STATUS_ONLINE,
                    'status_label' => 'Publié',
                    'type_id' => Publication::TYPE_TEXT,
                    'type_label' => 'text',
                    'category' => [
                        '@type' => 'UserPublicationCategory',
                        'id' => $category->getId(),
                        'title' => 'News',
                        'slug' => 'news',
                    ],
                ],
                [
                    '@id' => '/api/user_publications/' . $publication1->getId(),
                    '@type' => 'UserPublication',
                    'id' => $publication1->getId(),
                    'title' => 'Publication 1',
                    'slug' => 'publication-1',
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'edition_datetime' => '2024-01-02T10:00:00+00:00',
                    'status_id' => Publication::STATUS_DRAFT,
                    'status_label' => 'Brouillon',
                    'type_id' => Publication::TYPE_TEXT,
                    'type_label' => 'text',
                    'category' => [
                        '@type' => 'UserPublicationCategory',
                        'id' => $category->getId(),
                        'title' => 'News',
                        'slug' => 'news',
                    ],
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_collection_only_own_publications(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'user1', 'email' => 'user1@test.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'user2', 'email' => 'user2@test.com']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $user1Publication = PublicationFactory::new()->create([
            'author' => $user1,
            'subCategory' => $category,
            'title' => 'User 1 Publication',
            'slug' => 'user-1-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);

        PublicationFactory::new()->create([
            'author' => $user2,
            'subCategory' => $category,
            'title' => 'User 2 Publication',
            'slug' => 'user-2-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user1->_real());
        $this->client->request('GET', '/api/user/publications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $user1Publication->getId(),
                    'title' => 'User 1 Publication',
                ],
            ],
        ]);
    }

    public function test_get_collection_excludes_courses(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $publicationCategory = PublicationSubCategoryFactory::new()->asNews()->create();
        $courseCategory = PublicationSubCategoryFactory::new()->create([
            'title' => 'Course Category',
            'slug' => 'course-category',
            'type' => PublicationSubCategory::TYPE_COURSE,
        ]);

        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $publicationCategory,
            'title' => 'Publication',
            'slug' => 'publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $courseCategory,
            'title' => 'Course',
            'slug' => 'course',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $publication->getId(),
                    'title' => 'Publication',
                ],
            ],
        ]);
    }

    public function test_get_collection_filter_by_status(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $draftPublication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Draft Publication',
            'slug' => 'draft-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Online Publication',
            'slug' => 'online-publication',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications?status=' . Publication::STATUS_DRAFT);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $draftPublication->getId(),
                    'title' => 'Draft Publication',
                ],
            ],
        ]);
    }

    public function test_get_collection_filter_by_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $newsCategory = PublicationSubCategoryFactory::new()->asNews()->create();
        $articleCategory = PublicationSubCategoryFactory::new()->asArticle()->create();

        $newsPublication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $newsCategory,
            'title' => 'News Publication',
            'slug' => 'news-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $articleCategory,
            'title' => 'Article Publication',
            'slug' => 'article-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications?category=' . $newsCategory->getId());
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 1,
            'member' => [
                [
                    'id' => $newsPublication->getId(),
                    'title' => 'News Publication',
                ],
            ],
        ]);
    }

    public function test_get_collection_pagination_first_page(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        for ($i = 1; $i <= 15; $i++) {
            PublicationFactory::new()->create([
                'author' => $user,
                'subCategory' => $category,
                'title' => 'Publication ' . $i,
                'slug' => 'publication-' . $i,
                'status' => Publication::STATUS_DRAFT,
                'type' => Publication::TYPE_TEXT,
            ]);
        }

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications?itemsPerPage=10');
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
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        for ($i = 1; $i <= 15; $i++) {
            PublicationFactory::new()->create([
                'author' => $user,
                'subCategory' => $category,
                'title' => 'Publication ' . $i,
                'slug' => 'publication-' . $i,
                'status' => Publication::STATUS_DRAFT,
                'type' => Publication::TYPE_TEXT,
            ]);
        }

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications?itemsPerPage=10&page=2');
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
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $zebraPublication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Zebra Publication',
            'slug' => 'zebra-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);

        $alphaPublication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Alpha Publication',
            'slug' => 'alpha-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-02T10:00:00+00:00'),
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications?sortBy=title&sortOrder=asc');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'totalItems' => 2,
            'member' => [
                [
                    'id' => $alphaPublication->getId(),
                    'title' => 'Alpha Publication',
                ],
                [
                    'id' => $zebraPublication->getId(),
                    'title' => 'Zebra Publication',
                ],
            ],
        ]);
    }
}

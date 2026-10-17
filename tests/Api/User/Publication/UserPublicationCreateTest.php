<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Publication;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserPublicationCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('POST', '/api/user/publications', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_publication(): void
    {
        $publicationRepository = self::getContainer()->get(PublicationRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'My New Publication',
            'categoryId' => $category->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $publications = $publicationRepository->findBy(['author' => $user]);
        $this->assertCount(1, $publications);

        $createdPublication = $publications[0];
        $this->assertEquals('My New Publication', $createdPublication->title);
        $this->assertEquals(Publication::STATUS_DRAFT, $createdPublication->status);
        $this->assertEquals(Publication::TYPE_TEXT, $createdPublication->type);
        $this->assertSame('fr', $createdPublication->language);
        $this->assertEquals($category->id, $createdPublication->subCategory->id);

        $response = $this->getResponseAsArray();
        $this->assertSame('UserPublicationEdit', $response['@type']);
        $this->assertSame('My New Publication', $response['title']);
        $this->assertSame(Publication::STATUS_DRAFT, $response['status_id']);
        $this->assertSame($category->id, $response['category']['id']);
        $this->assertSame('News', $response['category']['title']);
        $this->assertSame('fr', $response['language']);
    }

    public function test_create_publication_takes_the_author_locale(): void
    {
        $publicationRepository = self::getContainer()->get(PublicationRepository::class);
        $user = UserFactory::new()->asBaseUser()->create(['locale' => 'en']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'My New Publication',
            'categoryId' => $category->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $publications = $publicationRepository->findBy(['author' => $user]);
        $this->assertCount(1, $publications);
        $this->assertSame('en', $publications[0]->language);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $publications[0]->id . '/edit',
            '@type' => 'UserPublicationEdit',
            'id' => $publications[0]->id,
            'title' => 'My New Publication',
            'slug' => 'my-new-publication-',
            'status_id' => Publication::STATUS_DRAFT,
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'language' => 'en',
            'translation_languages' => [],
            'tags' => [],
        ]);
    }

    public function test_create_publication_validation_error_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => '',
            'categoryId' => $category->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_publication_validation_error_short_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'ab',
            'categoryId' => $category->id,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_publication_validation_error_missing_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'My New Publication',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_publication_invalid_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'My New Publication',
            'categoryId' => 999999,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}

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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserPublicationCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
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

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'My New Publication',
            'categoryId' => $category->getId(),
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $publications = $publicationRepository->findBy(['author' => $user->_real()]);
        $this->assertCount(1, $publications);

        $createdPublication = $publications[0];
        $this->assertEquals('My New Publication', $createdPublication->getTitle());
        $this->assertEquals(Publication::STATUS_DRAFT, $createdPublication->getStatus());
        $this->assertEquals(Publication::TYPE_TEXT, $createdPublication->getType());
        $this->assertEquals($category->getId(), $createdPublication->getSubCategory()->getId());

        $this->assertJsonContains([
            '@type' => 'UserPublicationEdit',
            'title' => 'My New Publication',
            'status_id' => Publication::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->getId(),
                'title' => 'News',
            ],
        ]);
    }

    public function test_create_publication_validation_error_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => '',
            'categoryId' => $category->getId(),
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_publication_validation_error_short_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'ab',
            'categoryId' => $category->getId(),
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_publication_validation_error_missing_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'My New Publication',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_create_publication_invalid_category(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/publications', [
            'title' => 'My New Publication',
            'categoryId' => 999999,
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}

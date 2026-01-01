<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Gallery;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserGalleryCreateTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('POST', '/api/user/galleries', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_gallery(): void
    {
        $galleryRepository = self::getContainer()->get(GalleryRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/galleries', [
            'title' => 'My New Gallery',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $galleries = $galleryRepository->findBy(['author' => $user->_real()]);
        $this->assertCount(1, $galleries);

        $createdGallery = $galleries[0];
        $this->assertEquals('My New Gallery', $createdGallery->getTitle());
        $this->assertEquals(Gallery::STATUS_DRAFT, $createdGallery->getStatus());
        $this->assertNotNull($createdGallery->getSlug());

        $this->assertJsonContains([
            '@type' => 'UserGallery',
            'title' => 'My New Gallery',
            'status' => Gallery::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'image_count' => 0,
        ]);
    }

    public function test_create_gallery_validation_error_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/galleries', [
            'title' => '',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => "title: Le titre est requis\ntitle: Le titre doit contenir au moins 3 caracteres",
            'status' => 422,
            'type' => '/validation_errors/0=c1051bb4-d103-4f74-8988-acbcafc7fdc3;1=9ff3fdc4-b214-49db-8718-39c315e33d45',
            'description' => "title: Le titre est requis\ntitle: Le titre doit contenir au moins 3 caracteres",
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Le titre est requis',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'title',
                    'message' => 'Le titre doit contenir au moins 3 caracteres',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);
    }

    public function test_create_gallery_validation_error_short_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('POST', '/api/user/galleries', [
            'title' => 'ab',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'title' => 'An error occurred',
            'detail' => 'title: Le titre doit contenir au moins 3 caracteres',
            'status' => 422,
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'description' => 'title: Le titre doit contenir au moins 3 caracteres',
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Le titre doit contenir au moins 3 caracteres',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
        ]);
    }
}

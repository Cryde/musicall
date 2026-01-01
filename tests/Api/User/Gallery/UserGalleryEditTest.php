<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Gallery;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserGalleryEditTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_gallery(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'My Gallery',
            'description' => 'My description',
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId());
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'UserGalleryEdit',
            'id' => $gallery->getId(),
            'title' => 'My Gallery',
            'description' => 'My description',
            'status' => Gallery::STATUS_DRAFT,
        ]);
    }

    public function test_get_gallery_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $gallery = GalleryFactory::new()->create([
            'author' => $owner,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($otherUser->_real());
        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_gallery_not_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_ONLINE,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_gallery_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries/999999');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_patch_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->getId(), [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_patch_gallery(): void
    {
        $galleryRepository = self::getContainer()->get(GalleryRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'Original Title',
            'description' => 'Original description',
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->getId(), [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $updatedGallery = $galleryRepository->find($gallery->getId());
        $this->assertEquals('Updated Title', $updatedGallery->getTitle());
        $this->assertEquals('Updated description', $updatedGallery->getDescription());

        $this->assertJsonContains([
            '@type' => 'UserGalleryEdit',
            'id' => $gallery->getId(),
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);
    }

    public function test_patch_gallery_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $gallery = GalleryFactory::new()->create([
            'author' => $owner,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($otherUser->_real());
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->getId(), [
            'title' => 'Hacked Title',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_patch_gallery_not_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_ONLINE,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->getId(), [
            'title' => 'Updated Title',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_patch_gallery_validation_error_empty_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->getId(), [
            'title' => '',
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
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
}

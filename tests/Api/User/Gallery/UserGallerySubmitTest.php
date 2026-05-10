<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Gallery;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class UserGallerySubmitTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_submit_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->id . '/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_submit_success(): void
    {
        $galleryRepository = self::getContainer()->get(GalleryRepository::class);
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'My Gallery',
            'description' => 'A great gallery description',
            'status' => Gallery::STATUS_DRAFT,
        ]);
        $coverImage = GalleryImageFactory::new(['gallery' => $gallery])->create();
        $gallery->coverImage = $coverImage;
        \Zenstruck\Foundry\Persistence\save($gallery);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->id . '/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $updatedGallery = $galleryRepository->find($gallery->id);
        $this->assertEquals(Gallery::STATUS_PENDING, $updatedGallery->status);

        $response = $this->getResponseAsArray();
        $this->assertSame('UserGallery', $response['@type']);
        $this->assertSame($gallery->id, $response['id']);
        $this->assertSame(Gallery::STATUS_PENDING, $response['status']);
        $this->assertSame('En validation', $response['status_label']);
    }

    public function test_submit_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $gallery = GalleryFactory::new()->create([
            'author' => $owner,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($otherUser);
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->id . '/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'etes pas autorise a soumettre cette galerie',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'etes pas autorise a soumettre cette galerie',
        ]);
    }

    public function test_submit_already_submitted(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_PENDING,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->id . '/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette galerie ne peut pas etre soumise',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Cette galerie ne peut pas etre soumise',
        ]);
    }

    public function test_submit_already_published(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_ONLINE,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->id . '/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette galerie ne peut pas etre soumise',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Cette galerie ne peut pas etre soumise',
        ]);
    }

    public function test_submit_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/galleries/999999/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Galerie non trouvee',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Galerie non trouvee',
        ]);
    }

    public function test_submit_missing_description(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'My Gallery',
            'description' => null,
            'status' => Gallery::STATUS_DRAFT,
        ]);
        $coverImage = GalleryImageFactory::new(['gallery' => $gallery])->create();
        $gallery->coverImage = $coverImage;
        \Zenstruck\Foundry\Persistence\save($gallery);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->id . '/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez spécifier une description pour votre galerie',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Vous devez spécifier une description pour votre galerie',
        ]);
    }

    public function test_submit_missing_cover_image(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'My Gallery',
            'description' => 'A great description',
            'status' => Gallery::STATUS_DRAFT,
            'coverImage' => null,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/galleries/' . $gallery->id . '/submit', [], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez spécifier une image de couverture',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Vous devez spécifier une image de couverture',
        ]);
    }
}

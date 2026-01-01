<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Gallery;

use App\Entity\Gallery;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\Publication\GalleryImageFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserGalleryPreviewTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_preview_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId() . '/preview');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_preview_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'My Gallery',
            'slug' => 'my-gallery',
            'description' => 'A great description',
            'status' => Gallery::STATUS_DRAFT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);
        GalleryImageFactory::new(['gallery' => $gallery, 'imageName' => 'image1.jpg'])->create();
        GalleryImageFactory::new(['gallery' => $gallery, 'imageName' => 'image2.jpg'])->create();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId() . '/preview');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserGalleryPreview',
            '@id' => '/api/user/galleries/' . $gallery->getId() . '/preview',
            '@type' => 'UserGalleryPreview',
            'id' => $gallery->getId(),
            'title' => 'My Gallery',
            'slug' => 'my-gallery',
            'description' => 'A great description',
            'status' => Gallery::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'author_username' => 'base_admin',
            'images' => [
                [
                    'small' => 'http://musicall.test/media/cache/resolve/gallery_image_filter_small/images/gallery/' . $gallery->getId() . '/image1.jpg',
                    'medium' => 'http://musicall.test/media/cache/resolve/gallery_image_filter_medium/images/gallery/' . $gallery->getId() . '/image1.jpg',
                    'full' => 'http://musicall.test/media/cache/resolve/gallery_image_filter_full/images/gallery/' . $gallery->getId() . '/image1.jpg',
                ],
                [
                    'small' => 'http://musicall.test/media/cache/resolve/gallery_image_filter_small/images/gallery/' . $gallery->getId() . '/image2.jpg',
                    'medium' => 'http://musicall.test/media/cache/resolve/gallery_image_filter_medium/images/gallery/' . $gallery->getId() . '/image2.jpg',
                    'full' => 'http://musicall.test/media/cache/resolve/gallery_image_filter_full/images/gallery/' . $gallery->getId() . '/image2.jpg',
                ],
            ],
        ]);
    }

    public function test_preview_pending_gallery(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'My Pending Gallery',
            'slug' => 'my-pending-gallery',
            'description' => 'Pending description',
            'status' => Gallery::STATUS_PENDING,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId() . '/preview');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserGalleryPreview',
            '@id' => '/api/user/galleries/' . $gallery->getId() . '/preview',
            '@type' => 'UserGalleryPreview',
            'id' => $gallery->getId(),
            'title' => 'My Pending Gallery',
            'slug' => 'my-pending-gallery',
            'description' => 'Pending description',
            'status' => Gallery::STATUS_PENDING,
            'status_label' => 'En validation',
            'creation_datetime' => '2024-01-01T10:00:00+00:00',
            'author_username' => 'base_admin',
            'images' => [],
        ]);
    }

    public function test_preview_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $gallery = GalleryFactory::new()->create([
            'author' => $owner,
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($otherUser->_real());
        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId() . '/preview');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You are not the owner of this gallery',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You are not the owner of this gallery',
        ]);
    }

    public function test_preview_online_gallery(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $gallery = GalleryFactory::new()->create([
            'author' => $user,
            'status' => Gallery::STATUS_ONLINE,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries/' . $gallery->getId() . '/preview');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'This gallery is already online. View it directly.',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'This gallery is already online. View it directly.',
        ]);
    }

    public function test_preview_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries/999999/preview');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Gallery not found',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Gallery not found',
        ]);
    }
}

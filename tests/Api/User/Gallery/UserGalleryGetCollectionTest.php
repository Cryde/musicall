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

class UserGalleryGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('GET', '/api/user/galleries');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_collection(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $gallery1 = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'Gallery 1',
            'slug' => 'gallery-1',
            'description' => 'Description 1',
            'status' => Gallery::STATUS_DRAFT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);

        $gallery2 = GalleryFactory::new()->create([
            'author' => $user,
            'title' => 'Gallery 2',
            'slug' => 'gallery-2',
            'description' => 'Description 2',
            'status' => Gallery::STATUS_ONLINE,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-02T10:00:00+00:00'),
        ]);
        GalleryImageFactory::new(['gallery' => $gallery2])->create();
        GalleryImageFactory::new(['gallery' => $gallery2])->create();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/galleries');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserGallery',
            '@id' => '/api/user/galleries',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@type' => 'UserGallery',
                    '@id' => '/api/user_galleries/' . $gallery2->getId(),
                    'id' => $gallery2->getId(),
                    'title' => 'Gallery 2',
                    'slug' => 'gallery-2',
                    'status' => Gallery::STATUS_ONLINE,
                    'status_label' => 'En ligne',
                    'image_count' => 2,
                    'creation_datetime' => '2024-01-02T10:00:00+00:00',
                    'description' => 'Description 2',
                ],
                [
                    '@type' => 'UserGallery',
                    '@id' => '/api/user_galleries/' . $gallery1->getId(),
                    'id' => $gallery1->getId(),
                    'title' => 'Gallery 1',
                    'slug' => 'gallery-1',
                    'status' => Gallery::STATUS_DRAFT,
                    'status_label' => 'Brouillon',
                    'image_count' => 0,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'description' => 'Description 1',
                ],
            ],
        ]);
    }

    public function test_get_collection_only_own_galleries(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'user1', 'email' => 'user1@test.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'user2', 'email' => 'user2@test.com']);

        $user1Gallery = GalleryFactory::new()->create([
            'author' => $user1,
            'title' => 'User 1 Gallery',
            'slug' => 'user-1-gallery',
            'description' => 'User 1 Description',
            'status' => Gallery::STATUS_DRAFT,
            'creationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T10:00:00+00:00'),
        ]);

        GalleryFactory::new()->create([
            'author' => $user2,
            'title' => 'User 2 Gallery',
            'slug' => 'user-2-gallery',
            'status' => Gallery::STATUS_DRAFT,
        ]);

        $this->client->loginUser($user1->_real());
        $this->client->request('GET', '/api/user/galleries');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserGallery',
            '@id' => '/api/user/galleries',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@type' => 'UserGallery',
                    '@id' => '/api/user_galleries/' . $user1Gallery->getId(),
                    'id' => $user1Gallery->getId(),
                    'title' => 'User 1 Gallery',
                    'slug' => 'user-1-gallery',
                    'status' => Gallery::STATUS_DRAFT,
                    'status_label' => 'Brouillon',
                    'image_count' => 0,
                    'creation_datetime' => '2024-01-01T10:00:00+00:00',
                    'description' => 'User 1 Description',
                ],
            ],
        ]);
    }
}

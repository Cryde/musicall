<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Gallery;

use App\Entity\Gallery;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GalleryPendingListTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_pending_galleries_as_admin(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        GalleryFactory::new([
            'author' => $admin,
            'status' => Gallery::STATUS_DRAFT,
        ])->create();
        GalleryFactory::new([
            'author' => $admin,
            'status' => Gallery::STATUS_ONLINE,
        ])->create();

        $gallery = GalleryFactory::new([
            'author'              => $admin,
            'title'               => 'Ma galerie photo',
            'slug'                => 'ma-galerie-photo',
            'status'              => Gallery::STATUS_PENDING,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/galleries/pending');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/AdminPendingGallery',
            '@id'        => '/api/admin/galleries/pending',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/galleries/' . $gallery->_real()->getId(),
                    '@type'                => 'Gallery',
                    'id'                   => $gallery->_real()->getId(),
                    'title'                => 'Ma galerie photo',
                    'publication_datetime' => '2022-01-02T02:03:04+00:00',
                    'author'               => [
                        '@id'      => '/api/users/' . $admin->getId(),
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'cover_image'          => null,
                    'slug'                 => 'ma-galerie-photo',
                    'image_count'          => 0,
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_pending_galleries_not_logged(): void
    {
        $this->client->request('GET', '/api/admin/galleries/pending');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_pending_galleries_as_normal_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/galleries/pending');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}

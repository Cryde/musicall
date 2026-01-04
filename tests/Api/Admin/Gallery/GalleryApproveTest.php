<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Gallery;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class GalleryApproveTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_approve_gallery(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        // this one should be kept as DRAFT :
        $draft = GalleryFactory::new(['author' => $admin, 'status' => Gallery::STATUS_DRAFT])->create();

        $gallery = GalleryFactory::new([
            'author'              => $admin,
            'title'               => 'Ma galerie photo',
            'publicationDatetime' => null,
            'status'              => Gallery::STATUS_PENDING,
        ])->create();

        $this->assertSame(Gallery::STATUS_PENDING, $gallery->getStatus());
        $this->assertNull($gallery->getPublicationDatetime());

        $galleryId = $gallery->getId();
        $draftId = $draft->getId();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/galleries/' . $galleryId . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        /** @var GalleryRepository $galleryRepository */
        $galleryRepository = static::getContainer()->get(GalleryRepository::class);
        $refreshedDraft = $galleryRepository->find($draftId);
        $refreshedGallery = $galleryRepository->find($galleryId);

        $this->assertSame(Gallery::STATUS_DRAFT, $refreshedDraft->getStatus());
        $this->assertSame(Gallery::STATUS_ONLINE, $refreshedGallery->getStatus());
        $this->assertNotNull($refreshedGallery->getPublicationDatetime());
    }

    public function test_approve_gallery_with_no_admin(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $gallery = GalleryFactory::new(['author' => $user, 'status' => Gallery::STATUS_PENDING])->create();

        $this->client->loginUser($user);
        $this->client->request('POST', '/api/admin/galleries/' . $gallery->getId() . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_approve_gallery_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $gallery = GalleryFactory::new(['author' => $user, 'status' => Gallery::STATUS_PENDING])->create();

        $this->client->request('POST', '/api/admin/galleries/' . $gallery->getId() . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_approve_gallery_already_online(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $gallery = GalleryFactory::new([
            'author' => $admin,
            'status' => Gallery::STATUS_ONLINE,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/galleries/' . $gallery->getId() . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context'    => '/api/contexts/Error',
            '@id'         => '/api/errors/400',
            '@type'       => 'Error',
            'title'       => 'An error occurred',
            'detail'      => 'Only pending galleries can be approved',
            'status'      => 400,
            'type'        => '/errors/400',
            'description' => 'Only pending galleries can be approved',
        ]);
    }

    public function test_approve_gallery_draft(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();

        $gallery = GalleryFactory::new([
            'author' => $admin,
            'status' => Gallery::STATUS_DRAFT,
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/galleries/' . $gallery->getId() . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context'    => '/api/contexts/Error',
            '@id'         => '/api/errors/400',
            '@type'       => 'Error',
            'title'       => 'An error occurred',
            'detail'      => 'Only pending galleries can be approved',
            'status'      => 400,
            'type'        => '/errors/400',
            'description' => 'Only pending galleries can be approved',
        ]);
    }
}

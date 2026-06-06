<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Gallery;

use App\Entity\Gallery;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Repository\GalleryRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\GalleryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class GalleryModerationNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_approving_a_gallery_notifies_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'gallery_author', 'email' => 'author@test.com']);
        $admin = UserFactory::new()->asAdminUser()->create();
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $gallery = GalleryFactory::new([
            'author' => $author,
            'status' => Gallery::STATUS_PENDING,
            'slug' => 'ma-galerie-photo',
            'title' => 'Ma galerie photo',
        ])->create();
        $galleryId = $gallery->id;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/galleries/' . $galleryId . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The approve processor regenerates the slug, so read the persisted value rather than hardcode it.
        $refreshed = self::getContainer()->get(GalleryRepository::class)->find($galleryId);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::GalleryApproved, $notifications[0]->type);
        $this->assertSame([
            'gallery_id' => (string) $galleryId,
            'gallery_slug' => $refreshed->slug,
            'gallery_title' => 'Ma galerie photo',
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);
    }

    public function test_rejecting_a_gallery_notifies_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'gallery_author', 'email' => 'author@test.com']);
        $admin = UserFactory::new()->asAdminUser()->create();
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $gallery = GalleryFactory::new([
            'author' => $author,
            'status' => Gallery::STATUS_PENDING,
            'slug' => 'ma-galerie-photo',
            'title' => 'Ma galerie photo',
        ])->create();
        $galleryId = $gallery->id;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/galleries/' . $galleryId . '/reject', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::GalleryRejected, $notifications[0]->type);
        $this->assertSame([
            'gallery_id' => (string) $galleryId,
            'gallery_slug' => 'ma-galerie-photo',
            'gallery_title' => 'Ma galerie photo',
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);
    }

    public function test_admin_approving_their_own_gallery_does_not_self_notify(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $gallery = GalleryFactory::new([
            'author' => $admin,
            'status' => Gallery::STATUS_PENDING,
            'slug' => 'ma-galerie-photo',
            'title' => 'Ma galerie photo',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/galleries/' . $gallery->id . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0));
    }

    public function test_notification_failure_does_not_break_the_approval(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'gallery_author', 'email' => 'author@test.com']);
        $admin = UserFactory::new()->asAdminUser()->create();

        $gallery = GalleryFactory::new([
            'author' => $author,
            'status' => Gallery::STATUS_PENDING,
            'slug' => 'ma-galerie-photo',
            'title' => 'Ma galerie photo',
        ])->create();
        $galleryId = $gallery->id;

        // A notification failure must never roll back or 500 the moderation (epic #689 contract item 1).
        self::getContainer()->set(NotificationCreator::class, new readonly class extends NotificationCreator {
            public function __construct()
            {
            }

            public function create(User $recipient, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }

            public function createForRecipients(iterable $recipients, NotificationType $type, array $payload): void
            {
                throw new \RuntimeException('Notification creation failed');
            }
        });

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/galleries/' . $galleryId . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $approved = self::getContainer()->get(GalleryRepository::class)->find($galleryId);
        $this->assertSame(Gallery::STATUS_ONLINE, $approved->status);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0));
    }
}

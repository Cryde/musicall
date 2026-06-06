<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Publication;

use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Repository\Notification\NotificationRepository;
use App\Repository\PublicationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PublicationModerationNotificationTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_approving_a_publication_notifies_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'pub_author', 'email' => 'author@test.com']);
        $admin = UserFactory::new()->asAdminUser()->create();
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $sub = PublicationSubCategoryFactory::new()->asArticle()->create();
        $publication = PublicationFactory::new([
            'author' => $author,
            'status' => Publication::STATUS_PENDING,
            'subCategory' => $sub,
            'slug' => 'titre-de-la-publication',
            'title' => 'Titre de la publication',
        ])->create();
        $publicationId = $publication->id;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/publications/' . $publicationId . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The approve processor regenerates the slug, so read the persisted value rather than hardcode it.
        $refreshed = self::getContainer()->get(PublicationRepository::class)->find($publicationId);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::PublicationApproved, $notifications[0]->type);
        $this->assertSame([
            'publication_id' => (string) $publicationId,
            'publication_slug' => $refreshed->slug,
            'publication_title' => 'Titre de la publication',
            'is_course' => false,
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);
    }

    public function test_rejecting_a_publication_notifies_the_author(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'pub_author', 'email' => 'author@test.com']);
        $admin = UserFactory::new()->asAdminUser()->create();
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $sub = PublicationSubCategoryFactory::new()->asArticle()->create();
        $publication = PublicationFactory::new([
            'author' => $author,
            'status' => Publication::STATUS_PENDING,
            'subCategory' => $sub,
            'slug' => 'titre-de-la-publication',
            'title' => 'Titre de la publication',
        ])->create();
        $publicationId = $publication->id;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/publications/' . $publicationId . '/reject', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::PublicationRejected, $notifications[0]->type);
        $this->assertSame([
            'publication_id' => (string) $publicationId,
            'publication_slug' => 'titre-de-la-publication',
            'publication_title' => 'Titre de la publication',
            'is_course' => false,
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);
    }

    public function test_approving_a_course_sets_is_course_true(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'course_author', 'email' => 'author@test.com']);
        $admin = UserFactory::new()->asAdminUser()->create();
        $adminId = (string) $admin->id;
        $adminUsername = $admin->username;

        $sub = PublicationSubCategoryFactory::new()->asCourse()->create();
        $publication = PublicationFactory::new([
            'author' => $author,
            'status' => Publication::STATUS_PENDING,
            'subCategory' => $sub,
            'slug' => 'mon-cours',
            'title' => 'Mon cours',
        ])->create();
        $publicationId = $publication->id;

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/publications/' . $publicationId . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $refreshed = self::getContainer()->get(PublicationRepository::class)->find($publicationId);

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::PublicationApproved, $notifications[0]->type);
        $this->assertSame([
            'publication_id' => (string) $publicationId,
            'publication_slug' => $refreshed->slug,
            'publication_title' => 'Mon cours',
            'is_course' => true,
            'actor_id' => $adminId,
            'actor_username' => $adminUsername,
        ], $notifications[0]->payload);
    }

    public function test_admin_approving_their_own_publication_does_not_self_notify(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $sub = PublicationSubCategoryFactory::new()->asArticle()->create();
        $publication = PublicationFactory::new([
            'author' => $admin,
            'status' => Publication::STATUS_PENDING,
            'subCategory' => $sub,
            'slug' => 'mon-article',
            'title' => 'Mon article',
        ])->create();

        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/admin/publications/' . $publication->id . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0));
    }

    public function test_notification_failure_does_not_break_the_approval(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'pub_author', 'email' => 'author@test.com']);
        $admin = UserFactory::new()->asAdminUser()->create();

        $sub = PublicationSubCategoryFactory::new()->asArticle()->create();
        $publication = PublicationFactory::new([
            'author' => $author,
            'status' => Publication::STATUS_PENDING,
            'subCategory' => $sub,
            'slug' => 'titre-de-la-publication',
            'title' => 'Titre de la publication',
        ])->create();
        $publicationId = $publication->id;

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
        $this->client->request('POST', '/api/admin/publications/' . $publicationId . '/approve', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $approved = self::getContainer()->get(PublicationRepository::class)->find($publicationId);
        $this->assertSame(Publication::STATUS_ONLINE, $approved->status);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($author, 10, 0));
    }
}

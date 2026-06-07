<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceInvitationDeclineTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_decline_invitation(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee);
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->token . '/decline',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $invitationRepo = self::getContainer()->get(BandSpaceInvitationRepository::class);
        $updated = $invitationRepo->find($invitation->id);
        $this->assertSame(InvitationStatus::Declined, $updated->status);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Settings, $invitation->id);
        $this->assertCount(1, $activities);
        $this->assertSame('invitation_declined', $activities[0]->type);
        $this->assertSame(['email' => 'invitee@example.com'], $activities[0]->payload);
        $this->assertSame($invitee->id, $activities[0]->actor?->id);
    }

    public function test_decline_wrong_user(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $wrongUser = UserFactory::new()->create(['username' => 'wrong', 'email' => 'wrong@example.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($wrongUser);
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->token . '/decline',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette invitation ne vous est pas destinée',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Cette invitation ne vous est pas destinée',
        ]);
    }

    public function test_decline_notifies_the_inviter(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->client->loginUser($invitee);
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->token . '/decline',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $notificationRepository = self::getContainer()->get(NotificationRepository::class);
        $inviterNotifications = $notificationRepository->findForRecipient($admin, 10, 0);
        $this->assertCount(1, $inviterNotifications);
        $this->assertSame(NotificationType::BandSpaceInvitationDeclined, $inviterNotifications[0]->type);
        $this->assertSame([
            'band_space_id' => (string) $bandSpace->id,
            'band_space_name' => 'The Rockers',
            'actor_id' => (string) $invitee->id,
            'actor_username' => 'invitee',
        ], $inviterNotifications[0]->payload);

        // The decliner (actor) receives nothing.
        $this->assertCount(0, $notificationRepository->findForRecipient($invitee, 10, 0));
    }

    public function test_notification_failure_does_not_break_the_decline(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $invitee = UserFactory::new()->create(['username' => 'invitee', 'email' => 'invitee@example.com']);
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'invitee@example.com',
            'existingUser' => $invitee,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        // A notification failure must never roll back or 500 the decline (epic #689 contract item 1).
        self::getContainer()->set(NotificationCreator::class, $this->throwingNotificationCreator());

        $this->client->loginUser($invitee);
        $this->client->request(
            'POST',
            '/api/band_spaces/invitations/' . $invitation->token . '/decline',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $updated = self::getContainer()->get(BandSpaceInvitationRepository::class)->find($invitation->id);
        $this->assertSame(InvitationStatus::Declined, $updated->status);
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0));
    }

    private function throwingNotificationCreator(): NotificationCreator
    {
        return new readonly class extends NotificationCreator {
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
        };
    }
}

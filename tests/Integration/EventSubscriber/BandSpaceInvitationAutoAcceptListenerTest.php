<?php declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Event\UserRegisteredEvent;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Notification\NotificationRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceInvitationAutoAcceptListenerTest extends KernelTestCase
{
    public function test_auto_accepts_pending_invitation_on_registration(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $invitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'newuser@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $newUser = new User();
        $newUser->username = 'newuser';
        $newUser->email = 'newuser@example.com';
        $newUser->password = 'hashed';
        $newUser->profile = new UserProfile();
        $em->persist($newUser);
        $em->flush();

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch(new UserRegisteredEvent($newUser));

        $membershipRepo = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertTrue($membershipRepo->isMember($bandSpace, $newUser));

        $invitationRepo = self::getContainer()->get(BandSpaceInvitationRepository::class);
        $updated = $invitationRepo->find($invitation->id);
        $this->assertSame(InvitationStatus::Accepted, $updated->status);
        $this->assertSame($newUser->id, $updated->existingUser->id);
    }

    public function test_auto_accepts_multiple_invitations(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace1 = BandSpaceFactory::new()->create();
        $bandSpace2 = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace1, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace2, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace1,
            'invitedBy' => $admin,
            'email' => 'newuser@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();
        BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace2,
            'invitedBy' => $admin,
            'email' => 'newuser@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $newUser = new User();
        $newUser->username = 'newuser';
        $newUser->email = 'newuser@example.com';
        $newUser->password = 'hashed';
        $newUser->profile = new UserProfile();
        $em->persist($newUser);
        $em->flush();

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch(new UserRegisteredEvent($newUser));

        $membershipRepo = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertTrue($membershipRepo->isMember($bandSpace1, $newUser));
        $this->assertTrue($membershipRepo->isMember($bandSpace2, $newUser));

        // One inviter notification per accepted invitation (both invited by $admin).
        $this->assertCount(2, self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0));
    }

    public function test_ignores_expired_invitations(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'newuser@example.com',
            'expirationDatetime' => (new \DateTime())->modify('-1 day'),
        ])->create();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $newUser = new User();
        $newUser->username = 'newuser';
        $newUser->email = 'newuser@example.com';
        $newUser->password = 'hashed';
        $newUser->profile = new UserProfile();
        $em->persist($newUser);
        $em->flush();

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch(new UserRegisteredEvent($newUser));

        $membershipRepo = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertFalse($membershipRepo->isMember($bandSpace, $newUser));
    }

    public function test_no_pending_invitations(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $newUser = new User();
        $newUser->username = 'newuser';
        $newUser->email = 'newuser@example.com';
        $newUser->password = 'hashed';
        $newUser->profile = new UserProfile();
        $em->persist($newUser);
        $em->flush();

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch(new UserRegisteredEvent($newUser));

        // No exception thrown — just runs silently
        $this->assertTrue(true);
    }

    public function test_auto_accept_notifies_the_inviter(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'The Rockers'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'newuser@example.com',
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $newUser = new User();
        $newUser->username = 'newuser';
        $newUser->email = 'newuser@example.com';
        $newUser->password = 'hashed';
        $newUser->profile = new UserProfile();
        $em->persist($newUser);
        $em->flush();

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch(new UserRegisteredEvent($newUser));

        $notifications = self::getContainer()->get(NotificationRepository::class)->findForRecipient($admin, 10, 0);
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationType::BandSpaceInvitationAccepted, $notifications[0]->type);
        $this->assertSame([
            'band_space_id' => (string) $bandSpace->id,
            'band_space_name' => 'The Rockers',
            'actor_id' => (string) $newUser->id,
            'actor_username' => 'newuser',
        ], $notifications[0]->payload);
    }
}

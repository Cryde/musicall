<?php declare(strict_types=1);

namespace App\Tests\Integration\Command\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\InvitationStatus;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceInvitationFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ExpireInvitationsCommandTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $application = new Application(self::$kernel);
        $command = $application->find('app:band-space:expire-invitations');
        $this->commandTester = new CommandTester($command);
    }

    public function test_marks_expired_invitations(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $expiredInvitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'expired@example.com',
            'status' => InvitationStatus::Pending,
            'expirationDatetime' => (new \DateTime())->modify('-1 day'),
        ])->create();

        $validInvitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'valid@example.com',
            'status' => InvitationStatus::Pending,
            'expirationDatetime' => (new \DateTime())->modify('+7 days'),
        ])->create();

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('1 invitation(s) marquée(s) comme expirée(s)', $output);

        $repo = self::getContainer()->get(BandSpaceInvitationRepository::class);

        $expired = $repo->find($expiredInvitation->_real()->id);
        $this->assertSame(InvitationStatus::Expired, $expired->status);

        $valid = $repo->find($validInvitation->_real()->id);
        $this->assertSame(InvitationStatus::Pending, $valid->status);

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Settings, $expiredInvitation->_real()->id);
        $this->assertCount(1, $activities);
        $this->assertSame('invitation_expired', $activities[0]->type);
        $this->assertSame(['email' => 'expired@example.com'], $activities[0]->payload);
        $this->assertNull($activities[0]->actor);

        $validActivities = $activityRepo->findForResource($bandSpace->_real(), BandSpaceModule::Settings, $validInvitation->_real()->id);
        $this->assertCount(0, $validActivities);
    }

    public function test_does_not_touch_already_accepted_invitations(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $acceptedInvitation = BandSpaceInvitationFactory::new([
            'bandSpace' => $bandSpace,
            'invitedBy' => $admin,
            'email' => 'accepted@example.com',
            'status' => InvitationStatus::Accepted,
            'expirationDatetime' => (new \DateTime())->modify('-1 day'),
        ])->create();

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('0 invitation(s) marquée(s) comme expirée(s)', $output);

        $repo = self::getContainer()->get(BandSpaceInvitationRepository::class);
        $accepted = $repo->find($acceptedInvitation->_real()->id);
        $this->assertSame(InvitationStatus::Accepted, $accepted->status);
    }

    public function test_no_invitations_to_expire(): void
    {
        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('0 invitation(s) marquée(s) comme expirée(s)', $output);
    }
}

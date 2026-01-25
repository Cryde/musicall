<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\User;

use App\Entity\User\UserNotificationPreference;
use App\Repository\User\UserNotificationPreferenceRepository;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class DisableUserNotificationsCommandTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $application = new Application(self::$kernel);
        $command = $application->find('app:user:disable-notifications');
        $this->commandTester = new CommandTester($command);
    }

    public function test_command_fails_with_invalid_user_id(): void
    {
        $this->commandTester->execute(['user-id' => 'invalid-uuid']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('User with ID "invalid-uuid" not found', $output);
    }

    public function test_command_creates_preference_with_all_disabled(): void
    {
        $user = UserFactory::new()->create()->_real();

        $this->assertNull($user->getNotificationPreference());

        $this->commandTester->execute(['user-id' => $user->getId()]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('All notifications disabled', $output);

        // Verify the preference was created with all flags disabled
        $repository = self::getContainer()->get(UserNotificationPreferenceRepository::class);
        $preference = $repository->findOneBy(['user' => $user]);

        $this->assertNotNull($preference);
        $this->assertFalse($preference->isSiteNews());
        $this->assertFalse($preference->isWeeklyRecap());
        $this->assertFalse($preference->isMessageReceived());
        $this->assertFalse($preference->isPublicationComment());
        $this->assertFalse($preference->isForumReply());
        $this->assertFalse($preference->isMarketing());
        $this->assertFalse($preference->isActivityReminder());
    }

    public function test_command_updates_existing_preference_to_all_disabled(): void
    {
        $user = UserFactory::new()->create()->_real();

        // Create existing preference with some notifications enabled
        $preference = new UserNotificationPreference();
        $preference->setUser($user);
        $preference->setSiteNews(true);
        $preference->setWeeklyRecap(true);
        $preference->setMessageReceived(true);
        $user->setNotificationPreference($preference);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($preference);
        $em->flush();

        $this->commandTester->execute(['user-id' => $user->getId()]);

        $this->commandTester->assertCommandIsSuccessful();
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('All notifications disabled', $output);

        // Verify all preferences were disabled
        $em->refresh($preference);
        $this->assertFalse($preference->isSiteNews());
        $this->assertFalse($preference->isWeeklyRecap());
        $this->assertFalse($preference->isMessageReceived());
        $this->assertFalse($preference->isPublicationComment());
        $this->assertFalse($preference->isForumReply());
        $this->assertFalse($preference->isMarketing());
        $this->assertFalse($preference->isActivityReminder());
    }
}

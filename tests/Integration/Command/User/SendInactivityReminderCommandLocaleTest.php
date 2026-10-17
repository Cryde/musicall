<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\User;

use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Kept apart from SendInactivityReminderCommandTest, which stubs the email out: here it has to reach the mailer. */
#[ResetDatabase]
class SendInactivityReminderCommandLocaleTest extends KernelTestCase
{
    public function test_an_english_user_gets_the_english_template(): void
    {
        self::bootKernel();
        UserFactory::new()->create([
            'email' => 'inactive@example.com',
            'locale' => 'en',
            'confirmationDatetime' => new \DateTime('-1 year'),
            'lastLoginDatetime' => new \DateTime('-90 days'),
        ]);

        $commandTester = new CommandTester(new Application(self::$kernel)->find('app:user:send-inactivity-reminder'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'templateId', '20');
        $this->assertEmailTextBodyContains($email, 'Vous nous manquez sur MusicAll !');
        $this->assertEmailAddressContains($email, 'To', 'inactive@example.com');
    }
}

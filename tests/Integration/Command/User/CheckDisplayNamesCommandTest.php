<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\User;

use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\UserRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class CheckDisplayNamesCommandTest extends KernelTestCase
{
    public function test_lists_names_breaking_a_rule_without_changing_them(): void
    {
        [$impostor, $membership] = $this->seedNames();

        $tester = $this->tester();

        // Failure, so a scheduled run alerts on it.
        $this->assertSame(1, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('username_taken', $display);
        $this->assertStringContainsString('forbidden_character', $display);
        $this->assertStringContainsString('2 name(s) break a rule', $display);
        $this->assertStringNotContainsString('Alexandre Martin', $display);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame('user_admin', self::getContainer()->get(UserRepository::class)->find($impostor->id)?->profile->displayName);
    }

    public function test_fix_clears_only_the_names_breaking_a_rule(): void
    {
        [$impostor, $membership, $honest] = $this->seedNames();

        $tester = $this->tester();
        $tester->execute(['--fix' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('2 name(s) cleared', $tester->getDisplay());

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $users = self::getContainer()->get(UserRepository::class);
        $this->assertNull($users->find($impostor->id)?->profile->displayName);
        $this->assertSame('Alexandre Martin', $users->find($honest->id)?->profile->displayName);
        $this->assertNull(self::getContainer()->get(BandSpaceMembershipRepository::class)->find($membership->id)?->stageName);
    }

    public function test_nothing_to_report(): void
    {
        $tester = $this->tester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Every display name follows the rules', $tester->getDisplay());
    }

    /** @return array{object, object, object} */
    private function seedNames(): array
    {
        UserFactory::new()->asAdminUser()->create();
        $impostor = UserFactory::new()->create(['username' => 'impostor', 'email' => 'impostor@test.com']);
        $impostor->profile->displayName = 'user_admin';
        $honest = UserFactory::new()->create(['username' => 'androidtest_123', 'email' => 'androidtest@test.com']);
        $honest->profile->displayName = 'Alexandre Martin';
        $membership = BandSpaceMembershipFactory::new([
            'bandSpace' => BandSpaceFactory::new(),
            'user' => $honest,
            'stageName' => "Sam\u{202E}",
        ])->create();
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        return [$impostor, $membership, $honest];
    }

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('app:user:check-display-names'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\BandSpace;

use App\Service\BandSpace\BandSpaceMemberNames;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BandSpaceMemberNamesTest extends KernelTestCase
{
    public function test_a_user_is_named_per_band(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'androidtest_123', 'email' => 'androidtest@test.com']);
        $rockBand = BandSpaceFactory::new()->create();
        $jazzBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $rockBand, 'user' => $user, 'stageName' => 'Alex'])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $jazzBand, 'user' => $user])->create();

        $names = self::getContainer()->get(BandSpaceMemberNames::class);

        $this->assertSame('Alex', $names->nameOf($user, (string) $rockBand->id));
        $this->assertSame('androidtest_123', $names->nameOf($user, (string) $jazzBand->id));
    }

    public function test_a_user_with_no_membership_falls_back_to_the_username(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);
        $band = BandSpaceFactory::new()->create();

        $this->assertSame('outsider', self::getContainer()->get(BandSpaceMemberNames::class)->nameOf($user, (string) $band->id));
    }

    /** The FrankenPHP worker keeps the service between requests, so a reset must drop the memo. */
    public function test_reset_forgets_names_read_earlier(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'androidtest_123', 'email' => 'androidtest@test.com']);
        $band = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $band, 'user' => $user, 'stageName' => 'Alex'])->create();
        $names = self::getContainer()->get(BandSpaceMemberNames::class);
        $this->assertSame('Alex', $names->nameOf($user, (string) $band->id));

        $membership->stageName = 'Alexandre';
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->assertSame('Alex', $names->nameOf($user, (string) $band->id), 'Memoized within a request');
        $names->reset();
        $this->assertSame('Alexandre', $names->nameOf($user, (string) $band->id));
    }
}

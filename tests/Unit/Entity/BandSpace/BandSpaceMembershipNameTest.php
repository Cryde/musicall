<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class BandSpaceMembershipNameTest extends TestCase
{
    public function test_the_stage_name_wins_over_the_username(): void
    {
        $this->assertSame('Alex', BandSpaceMembership::nameFor('Alex', 'androidtest_123', false));
    }

    public function test_without_a_stage_name_the_username_is_used(): void
    {
        $this->assertSame('androidtest_123', BandSpaceMembership::nameFor(null, 'androidtest_123', false));
    }

    /** DeleteAccountProcedure keeps the stage name on the membership, so it must not leak the person back. */
    public function test_a_deleted_account_is_labelled_whatever_its_stage_name(): void
    {
        $this->assertSame(User::DELETED_DISPLAY_NAME, BandSpaceMembership::nameFor('Alex', 'deleted_c7c9f2e1', true));
        $this->assertSame(User::DELETED_DISPLAY_NAME, BandSpaceMembership::nameFor(null, 'deleted_c7c9f2e1', true));
    }

    public function test_display_name_applies_the_rule_to_the_membership(): void
    {
        $user = new User();
        $user->username = 'deleted_c7c9f2e1';
        $user->deletionDatetime = new \DateTimeImmutable('2026-09-01');
        $membership = new BandSpaceMembership();
        $membership->user = $user;
        $membership->stageName = 'Alex';

        $this->assertSame(User::DELETED_DISPLAY_NAME, $membership->displayName());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\User\UserProfile;
use PHPUnit\Framework\TestCase;

class UserPublicNameTest extends TestCase
{
    public function test_a_public_profile_name_is_used(): void
    {
        $this->assertSame('Alexandre Martin', User::publicNameFor('androidtest_123', false, 'Alexandre Martin', true));
    }

    public function test_a_private_profile_keeps_its_name_to_itself(): void
    {
        $this->assertSame('androidtest_123', User::publicNameFor('androidtest_123', false, 'Alexandre Martin', false));
    }

    public function test_a_blank_profile_name_falls_back_to_the_username(): void
    {
        $this->assertSame('androidtest_123', User::publicNameFor('androidtest_123', false, '   ', true));
        $this->assertSame('androidtest_123', User::publicNameFor('androidtest_123', false, null, true));
    }

    public function test_a_closed_account_is_labelled(): void
    {
        $this->assertSame(User::DELETED_DISPLAY_NAME, User::publicNameFor('deleted_c7c9f2e1', true, 'Alexandre Martin', true));
    }

    public function test_public_name_reads_the_profile(): void
    {
        $user = new User();
        $user->username = 'androidtest_123';
        $user->profile = new UserProfile();
        $user->profile->displayName = 'Alexandre Martin';

        $this->assertSame('Alexandre Martin', $user->publicName());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mercure;

use App\Mercure\MercureTopic;
use PHPUnit\Framework\TestCase;

class MercureTopicTest extends TestCase
{
    /**
     * Pinned rather than obvious. A publisher and a subscriber that disagree on this string deliver
     * nothing, silently, and nothing else in the stack would notice: the hub has no idea the two are
     * meant to be talking about the same thing.
     */
    public function test_a_user_notification_topic_is_scoped_to_that_user(): void
    {
        $this->assertSame(
            '/users/d1be73fc-b0c4-4530-a30a-d41f43e6ebea/notifications',
            MercureTopic::userNotifications('d1be73fc-b0c4-4530-a30a-d41f43e6ebea')
        );
    }
}

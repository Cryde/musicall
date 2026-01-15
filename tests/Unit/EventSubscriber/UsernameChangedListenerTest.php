<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\Event\UsernameChangedEvent;
use App\EventSubscriber\UsernameChangedListener;
use App\Service\Mail\Brevo\User\UsernameChangedEmail;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UsernameChangedListenerTest extends TestCase
{
    private UsernameChangedEmail&MockObject $usernameChangedEmail;
    private UsernameChangedListener $listener;

    protected function setUp(): void
    {
        $this->usernameChangedEmail = $this->createMock(UsernameChangedEmail::class);
        $this->listener = new UsernameChangedListener($this->usernameChangedEmail);
    }

    public function test_it_sends_email_when_username_changes(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $oldUsername = 'old_username';
        $newUsername = 'new_username';
        $changedAt = new \DateTimeImmutable('2024-01-15 10:30:00');

        $event = new UsernameChangedEvent($user, $oldUsername, $newUsername, $changedAt);

        $this->usernameChangedEmail
            ->expects($this->once())
            ->method('send')
            ->with(
                'test@example.com',
                'old_username',
                'new_username',
                $changedAt
            );

        ($this->listener)($event);
    }
}

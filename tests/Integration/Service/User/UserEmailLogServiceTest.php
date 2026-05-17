<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\User;

use App\Enum\User\UserEmailType;
use App\Repository\User\UserEmailLogRepository;
use App\Service\User\UserEmailLogService;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class UserEmailLogServiceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    public function test_log_creates_email_log_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->assertSame(0, $this->getRepository()->count());

        $this->getService()->log($user, UserEmailType::WELCOME);

        $this->assertSame(1, $this->getRepository()->count());

        $log = $this->getRepository()->findOneByUserAndType($user, UserEmailType::WELCOME);
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user->id);
        $this->assertSame(UserEmailType::WELCOME, $log->emailType);
        $this->assertNull($log->referenceId);
        $this->assertNull($log->metadata);
    }

    public function test_log_creates_entry_with_reference_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $announceId = 'announce-uuid-123';

        $this->getService()->log($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, $announceId);

        $log = $this->getRepository()->findOneByUserAndType($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, $announceId);
        $this->assertNotNull($log);
        $this->assertSame($announceId, $log->referenceId);
    }

    public function test_log_creates_entry_with_metadata(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $metadata = ['announce_title' => 'Guitariste cherche groupe'];

        $this->getService()->log($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-123', $metadata);

        $log = $this->getRepository()->findOneByUserAndType($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-123');
        $this->assertNotNull($log);
        $this->assertSame($metadata, $log->metadata);
    }

    public function test_has_been_sent_returns_false_when_no_log_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->assertFalse($this->getService()->hasBeenSent($user, UserEmailType::PROFILE_COMPLETENESS));
    }

    public function test_has_been_sent_returns_true_when_log_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->getService()->log($user, UserEmailType::PROFILE_COMPLETENESS);

        $this->assertTrue($this->getService()->hasBeenSent($user, UserEmailType::PROFILE_COMPLETENESS));
    }

    public function test_has_been_sent_checks_reference_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->getService()->log($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-1');

        $this->assertTrue($this->getService()->hasBeenSent($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-1'));
        $this->assertFalse($this->getService()->hasBeenSent($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-2'));
    }

    public function test_has_been_sent_is_user_specific(): void
    {
        $user1 = UserFactory::new()->create();
        $user2 = UserFactory::new()->create();

        $this->getService()->log($user1, UserEmailType::WELCOME);

        $this->assertTrue($this->getService()->hasBeenSent($user1, UserEmailType::WELCOME));
        $this->assertFalse($this->getService()->hasBeenSent($user2, UserEmailType::WELCOME));
    }

    public function test_has_been_sent_since_returns_false_when_log_is_older(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->getService()->log($user, UserEmailType::INACTIVITY_REMINDER);

        $tomorrow = new DateTimeImmutable('+1 day');
        $this->assertFalse($this->getService()->hasBeenSentSince($user, UserEmailType::INACTIVITY_REMINDER, $tomorrow));
    }

    public function test_has_been_sent_since_returns_true_when_log_is_recent(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->getService()->log($user, UserEmailType::INACTIVITY_REMINDER);

        $yesterday = new DateTimeImmutable('-1 day');
        $this->assertTrue($this->getService()->hasBeenSentSince($user, UserEmailType::INACTIVITY_REMINDER, $yesterday));
    }

    public function test_has_been_sent_since_checks_reference_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $yesterday = new DateTimeImmutable('-1 day');

        $this->getService()->log($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-1');

        $this->assertTrue($this->getService()->hasBeenSentSince($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, $yesterday, 'announce-1'));
        $this->assertFalse($this->getService()->hasBeenSentSince($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, $yesterday, 'announce-2'));
    }

    private function getService(): UserEmailLogService
    {
        return self::getContainer()->get(UserEmailLogService::class);
    }

    private function getRepository(): UserEmailLogRepository
    {
        return self::getContainer()->get(UserEmailLogRepository::class);
    }
}

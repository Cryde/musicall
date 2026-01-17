<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\User;

use App\Enum\User\UserEmailType;
use App\Repository\User\UserEmailLogRepository;
use App\Service\User\UserEmailLogService;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserEmailLogServiceTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    public function test_log_creates_email_log_entry(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->assertSame(0, $this->getRepository()->count());

        $this->getService()->log($user, UserEmailType::WELCOME);

        $this->assertSame(1, $this->getRepository()->count());

        $log = $this->getRepository()->findOneByUserAndType($user, UserEmailType::WELCOME);
        $this->assertNotNull($log);
        $this->assertSame($user->getId(), $log->getUser()->getId());
        $this->assertSame(UserEmailType::WELCOME, $log->getEmailType());
        $this->assertNull($log->getReferenceId());
        $this->assertNull($log->getMetadata());
    }

    public function test_log_creates_entry_with_reference_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $announceId = 'announce-uuid-123';

        $this->getService()->log($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, $announceId);

        $log = $this->getRepository()->findOneByUserAndType($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, $announceId);
        $this->assertNotNull($log);
        $this->assertSame($announceId, $log->getReferenceId());
    }

    public function test_log_creates_entry_with_metadata(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $metadata = ['announce_title' => 'Guitariste cherche groupe'];

        $this->getService()->log($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-123', $metadata);

        $log = $this->getRepository()->findOneByUserAndType($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-123');
        $this->assertNotNull($log);
        $this->assertSame($metadata, $log->getMetadata());
    }

    public function test_has_been_sent_returns_false_when_no_log_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->assertFalse($this->getService()->hasBeenSent($user, UserEmailType::PROFILE_COMPLETENESS));
    }

    public function test_has_been_sent_returns_true_when_log_exists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->getService()->log($user, UserEmailType::PROFILE_COMPLETENESS);

        $this->assertTrue($this->getService()->hasBeenSent($user, UserEmailType::PROFILE_COMPLETENESS));
    }

    public function test_has_been_sent_checks_reference_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->getService()->log($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-1');

        $this->assertTrue($this->getService()->hasBeenSent($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-1'));
        $this->assertFalse($this->getService()->hasBeenSent($user, UserEmailType::ANNOUNCE_RENEWAL_REMINDER, 'announce-2'));
    }

    public function test_has_been_sent_is_user_specific(): void
    {
        $user1 = UserFactory::new()->create()->_real();
        $user2 = UserFactory::new()->create()->_real();

        $this->getService()->log($user1, UserEmailType::WELCOME);

        $this->assertTrue($this->getService()->hasBeenSent($user1, UserEmailType::WELCOME));
        $this->assertFalse($this->getService()->hasBeenSent($user2, UserEmailType::WELCOME));
    }

    public function test_has_been_sent_since_returns_false_when_log_is_older(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->getService()->log($user, UserEmailType::INACTIVITY_REMINDER);

        $tomorrow = new DateTimeImmutable('+1 day');
        $this->assertFalse($this->getService()->hasBeenSentSince($user, UserEmailType::INACTIVITY_REMINDER, $tomorrow));
    }

    public function test_has_been_sent_since_returns_true_when_log_is_recent(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->getService()->log($user, UserEmailType::INACTIVITY_REMINDER);

        $yesterday = new DateTimeImmutable('-1 day');
        $this->assertTrue($this->getService()->hasBeenSentSince($user, UserEmailType::INACTIVITY_REMINDER, $yesterday));
    }

    public function test_has_been_sent_since_checks_reference_id(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
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

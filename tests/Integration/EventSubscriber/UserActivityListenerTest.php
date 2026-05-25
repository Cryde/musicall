<?php declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\UserActivityListener;
use App\Repository\UserRepository;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Listener is disabled globally in test env (see services.yaml when@test:)
 * so the suite doesn't pay an UPDATE-per-request; this test exercises it
 * directly to verify the throttle + auth-gating logic.
 */
#[ResetDatabase]
class UserActivityListenerTest extends KernelTestCase
{
    public function test_authenticated_request_sets_last_activity_when_field_is_null(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => null]);

        $this->invokeListener($user);

        $reloaded = $this->reload($user);
        $this->assertNotNull($reloaded->lastActivityDatetime);
        $age = (new DateTimeImmutable())->getTimestamp() - $reloaded->lastActivityDatetime->getTimestamp();
        $this->assertLessThan(5, $age, 'set value should be very close to now');
    }

    public function test_authenticated_request_refreshes_stale_last_activity(): void
    {
        $stale = new DateTimeImmutable('-10 minutes');
        $user = UserFactory::createOne(['lastActivityDatetime' => $stale]);

        $this->invokeListener($user);

        $reloaded = $this->reload($user);
        $this->assertGreaterThan(
            $stale->getTimestamp(),
            $reloaded->lastActivityDatetime->getTimestamp(),
            'A 10-minute-stale lastActivityDatetime must be refreshed',
        );
    }

    public function test_does_not_write_when_inside_throttle_window(): void
    {
        $recent = new DateTimeImmutable('-30 seconds');
        $user = UserFactory::createOne(['lastActivityDatetime' => $recent]);

        $this->invokeListener($user);

        $reloaded = $this->reload($user);
        $this->assertSame(
            $recent->getTimestamp(),
            $reloaded->lastActivityDatetime->getTimestamp(),
            'lastActivityDatetime must NOT be updated when previous write is inside the 60s throttle window',
        );
    }

    public function test_unauthenticated_request_is_a_no_op(): void
    {
        $user = UserFactory::createOne(['lastActivityDatetime' => null]);

        $this->invokeListener(null);

        $reloaded = $this->reload($user);
        $this->assertNull($reloaded->lastActivityDatetime);
    }

    public function test_subrequest_is_a_no_op(): void
    {
        $stale = new DateTimeImmutable('-10 minutes');
        $user = UserFactory::createOne(['lastActivityDatetime' => $stale]);

        $this->invokeListener($user, mainRequest: false);

        $reloaded = $this->reload($user);
        $this->assertSame($stale->getTimestamp(), $reloaded->lastActivityDatetime->getTimestamp());
    }

    private function invokeListener(?User $authenticatedUser, bool $mainRequest = true): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($authenticatedUser);

        $em = self::getContainer()->get(EntityManagerInterface::class);

        $listener = new UserActivityListener($security, $em);

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );

        $listener($event);

        // Force a clean read in the next reload() call.
        $em->clear();
    }

    private function reload(User $user): User
    {
        return self::getContainer()->get(UserRepository::class)->find($user->id);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\ApiResource\Notification\UserNotification;
use App\Enum\Notification\NotificationType;
use App\Service\Notification\Enricher\NotificationEnricherInterface;
use App\Service\Notification\NotificationFeedEnricher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NotificationFeedEnricherTest extends TestCase
{
    public function test_a_failing_enricher_degrades_to_stored_payload_and_never_breaks_the_feed(): void
    {
        $failing = $this->enricherFor(
            NotificationType::BandSpaceTaskAssignment,
            static function (array $notifications): void {
                throw new \RuntimeException('boom');
            },
        );
        $healthy = $this->enricherFor(
            NotificationType::BandSpaceInvitation,
            static function (array $notifications): void {
                foreach ($notifications as $notification) {
                    $notification->payload['invitation_status'] = 'expired';
                }
            },
        );

        $brokenGroup = $this->notification(NotificationType::BandSpaceTaskAssignment, ['task_title' => 'Titre stocké']);
        $healthyGroup = $this->notification(NotificationType::BandSpaceInvitation, ['invitation_status' => 'pending']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $enricher = new NotificationFeedEnricher([$failing, $healthy], $logger);
        $enricher->enrich([$brokenGroup, $healthyGroup]);

        // The failing enricher's group keeps its stored point-in-time payload; no exception bubbled up.
        self::assertSame(['task_title' => 'Titre stocké'], $brokenGroup->payload);
        // A failure in one type does not stop other types from being enriched.
        self::assertSame(['invitation_status' => 'expired'], $healthyGroup->payload);
    }

    private function enricherFor(NotificationType $type, \Closure $enrich): NotificationEnricherInterface
    {
        return new class($type, $enrich) implements NotificationEnricherInterface {
            public function __construct(
                private readonly NotificationType $type,
                private readonly \Closure $enrich,
            ) {
            }

            public function getType(): NotificationType
            {
                return $this->type;
            }

            public function enrich(array $notifications): void
            {
                ($this->enrich)($notifications);
            }
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notification(NotificationType $type, array $payload): UserNotification
    {
        $notification = new UserNotification();
        $notification->id = '00000000-0000-0000-0000-000000000000';
        $notification->type = $type->value;
        $notification->payload = $payload;
        $notification->creationDatetime = '2026-05-31T00:00:00+00:00';

        return $notification;
    }
}

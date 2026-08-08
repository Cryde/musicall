<?php

declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Puts a space on the deletion clock: stores the due date app:band-space:purge reads and logs the entry
 * the settings activity feed shows.
 *
 * Shared by the admin who asks for the deletion and by the account-deletion sweep that schedules a space
 * whose last member is gone, so the grace period announced in the privacy policy is defined once.
 *
 * Flushing and announcing are left to the caller: both callers already own a transaction boundary, and
 * the notification has to go out after the commit per the epic #689 contract.
 */
readonly class BandSpaceDeletionScheduler
{
    /**
     * Kept in sync with the durations announced in the privacy policy and the terms.
     */
    private const int GRACE_PERIOD_DAYS = 30;

    public function __construct(
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
    ) {
    }

    public function schedule(BandSpace $bandSpace, User $actor): DateTimeImmutable
    {
        $scheduledFor = new DateTimeImmutable(sprintf('+%d days', self::GRACE_PERIOD_DAYS));
        $bandSpace->deletionScheduledDatetime = $scheduledFor;

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Settings,
            type: BandSpaceSettingsActivityType::DeletionScheduled,
            resourceId: (string) $bandSpace->id,
            actor: $actor,
            payload: ['scheduled_for' => $scheduledFor->format(DateTimeInterface::ATOM)],
        );

        return $scheduledFor;
    }
}

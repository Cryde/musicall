<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\ApiResource\Notification\UserNotification;
use App\Entity\User;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\AgendaEntryRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;

/**
 * Refreshes a band-space agenda-entry notification's `entry_title`, `event_datetime` and
 * `is_all_day` at feed-read (#722), so it stays accurate after the entry is renamed, rescheduled or
 * switched between a day and a moment. The agenda has no per-entry/per-date deep-link, so all three
 * are load-bearing: `is_all_day` is what tells the feed to read the datetime as a written day rather
 * than an instant, which is the whole difference west of UTC (#877). Batched: two queries for the
 * whole page. A deleted entry keeps its last-known stored values (graceful staleness), and a
 * notification written before `is_all_day` existed carries no such value: the feed reads a missing
 * flag as false, which is how those datetimes were being read when they were stored.
 *
 * Like the task-title refresh, it follows the reader's current access (#817): a recipient who has
 * left the band space keeps the title and date as they stood when they were told, rather than a live
 * feed of where and when the band is now rehearsing.
 */
readonly class AgendaEntryNotificationEnricher implements NotificationEnricherInterface
{
    public function __construct(
        private AgendaEntryRepository $agendaEntryRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
    ) {
    }

    public function getType(): NotificationType
    {
        return NotificationType::BandSpaceAgendaEntryCreated;
    }

    /**
     * @param UserNotification[] $notifications
     */
    public function enrich(array $notifications, User $recipient): void
    {
        $ids = [];
        foreach ($notifications as $notification) {
            $entryId = $notification->payload['agenda_entry_id'] ?? null;
            if (is_string($entryId)) {
                $ids[] = $entryId;
            }
        }

        if ($ids === []) {
            return;
        }

        $entries = $this->agendaEntryRepository->findByIds($ids);
        if ($entries === []) {
            return;
        }

        $bandSpaceIds = [];
        foreach ($entries as $entry) {
            $bandSpaceId = (string) $entry->bandSpace->id;
            $bandSpaceIds[$bandSpaceId] = $bandSpaceId;
        }

        $activeBandSpaceIds = $this->bandSpaceMembershipRepository->findActiveBandSpaceIdsForUser(
            $recipient,
            array_values($bandSpaceIds),
        );

        $entriesById = [];
        foreach ($entries as $entry) {
            if (isset($activeBandSpaceIds[(string) $entry->bandSpace->id])) {
                $entriesById[(string) $entry->id] = $entry;
            }
        }

        foreach ($notifications as $notification) {
            $entryId = $notification->payload['agenda_entry_id'] ?? null;
            if (is_string($entryId) && isset($entriesById[$entryId])) {
                $entry = $entriesById[$entryId];
                $notification->payload['entry_title'] = $entry->title;
                $notification->payload['event_datetime'] = $entry->eventDatetime->format(\DateTimeInterface::ATOM);
                $notification->payload['is_all_day'] = $entry->isAllDay;
            }
        }
    }
}

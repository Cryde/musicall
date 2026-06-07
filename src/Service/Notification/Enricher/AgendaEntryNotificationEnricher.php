<?php declare(strict_types=1);

namespace App\Service\Notification\Enricher;

use App\ApiResource\Notification\UserNotification;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\AgendaEntryRepository;

/**
 * Refreshes a band-space agenda-entry notification's `entry_title` + `event_datetime` at feed-read
 * (#722), so it stays accurate after the entry is renamed or rescheduled. The agenda has no
 * per-entry/per-date deep-link, so both fields are load-bearing. Batched: one `id IN (...)` query for
 * the whole page. A deleted entry keeps its last-known stored values (graceful staleness).
 */
readonly class AgendaEntryNotificationEnricher implements NotificationEnricherInterface
{
    public function __construct(private AgendaEntryRepository $agendaEntryRepository)
    {
    }

    public function getType(): NotificationType
    {
        return NotificationType::BandSpaceAgendaEntryCreated;
    }

    /**
     * @param UserNotification[] $notifications
     */
    public function enrich(array $notifications): void
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

        $entriesById = [];
        foreach ($this->agendaEntryRepository->findByIds($ids) as $entry) {
            $entriesById[(string) $entry->id] = $entry;
        }

        foreach ($notifications as $notification) {
            $entryId = $notification->payload['agenda_entry_id'] ?? null;
            if (is_string($entryId) && isset($entriesById[$entryId])) {
                $entry = $entriesById[$entryId];
                $notification->payload['entry_title'] = $entry->title;
                $notification->payload['event_datetime'] = $entry->eventDatetime->format(\DateTimeInterface::ATOM);
            }
        }
    }
}

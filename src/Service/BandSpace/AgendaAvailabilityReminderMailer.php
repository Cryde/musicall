<?php

declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Service\Mail\Brevo\BandSpace\AgendaAvailabilityReminderEmail;
use App\Service\User\UserNotificationPreferenceChecker;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/** The email of « Relancer » (#1418): the reminder is already sent, so a failure is logged, never thrown. */
readonly class AgendaAvailabilityReminderMailer
{
    public function __construct(
        private AgendaAvailabilityReminderEmail $email,
        private UserNotificationPreferenceChecker $preferenceChecker,
        private RouterInterface $router,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<User> $recipients
     */
    public function send(
        array $recipients,
        User $actor,
        BandSpace $bandSpace,
        AgendaEntry $entry,
        string $occurrenceDate,
        DateTimeImmutable $occurrenceStart,
    ): void {
        // The agenda opens this date's drawer from these two parameters, as for the bell's link.
        $agendaUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL)
            . 'band/' . $bandSpace->id . '/agenda?'
            . http_build_query(['entry' => (string) $entry->id, 'occurrence' => $occurrenceDate]);

        foreach ($recipients as $recipient) {
            if (!$this->preferenceChecker->canReceiveBandAgendaReminderNotification($recipient)) {
                continue;
            }
            try {
                $this->email->send(
                    $recipient->email,
                    $recipient->username,
                    $actor->username,
                    $bandSpace->name,
                    $entry->title,
                    AgendaOccurrenceLabel::format($occurrenceStart, $entry->isAllDay, $recipient->locale),
                    $agendaUrl,
                    $recipient->locale,
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send the availability reminder email', [
                    'agenda_entry_id' => (string) $entry->id,
                    'recipient_id' => (string) $recipient->id,
                    'exception' => $e,
                ]);
            }
        }
    }
}

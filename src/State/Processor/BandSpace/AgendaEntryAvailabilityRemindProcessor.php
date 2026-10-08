<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryAvailabilityReminder;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\AgendaOccurrenceLocator;
use App\Service\Builder\BandSpace\AgendaEntryAvailabilityBuilder;
use App\Service\Notification\NotificationCreator;
use DateTimeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * « Relancer » (#1000): asks the members who have neither answered nor declared an absence whether
 * they can make one date. The notification is the action itself, so it is created here rather than
 * by a listener.
 *
 * The author and the admins only, once every twelve hours per date: it pushes to phones.
 *
 * @implements ProcessorInterface<AgendaEntryAvailabilityReminder, void>
 */
readonly class AgendaEntryAvailabilityRemindProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private AgendaOccurrenceLocator $occurrenceLocator,
        private AgendaEntryAvailabilityBuilder $availabilityBuilder,
        private NotificationCreator $notificationCreator,
        private RateLimiterFactoryInterface $agendaAvailabilityReminderLimiter,
        private Security $security,
    ) {
    }

    /**
     * @param AgendaEntryAvailabilityReminder $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);
        [$entry, $occurrenceDate, $occurrenceStart] = $this->occurrenceLocator->locate($bandSpace, (string) $uriVariables['entryId'], $data->occurrenceDate);

        if ((string) $entry->creator->id !== (string) $user->id && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul l\'auteur de l\'événement ou un administrateur peut relancer les membres');
        }

        if (AgendaOccurrenceLocator::isPast($occurrenceDate)) {
            throw new UnprocessableEntityHttpException('Cette date est passée, les disponibilités ne peuvent plus changer');
        }

        $recipients = [];
        foreach ($this->availabilityBuilder->pendingMemberships($bandSpace, $entry, $occurrenceDate) as $pending) {
            if ((string) $pending->user->id !== (string) $user->id) {
                $recipients[] = $pending->user;
            }
        }
        if ($recipients === []) {
            return;
        }

        // After the checks, so a refused or empty reminder does not use up the window.
        if (!$this->agendaAvailabilityReminderLimiter->create($entry->id . '|' . $occurrenceDate)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Les membres ont déjà été relancés pour cette date, réessayez plus tard');
        }

        $this->notificationCreator->createForRecipients($recipients, NotificationType::BandSpaceAgendaAvailabilityRequested, [
            'band_space_id' => (string) $bandSpace->id,
            'band_space_name' => $bandSpace->name,
            'agenda_entry_id' => (string) $entry->id,
            'entry_title' => $entry->title,
            'occurrence_date' => $occurrenceDate,
            // The occurrence, not the series anchor, with the all day flag for the same reason as
            // BandSpaceAgendaEntryCreatedListener: a pinned all day date read as an instant shifts a day.
            'event_datetime' => $occurrenceStart->format(DateTimeInterface::ATOM),
            'is_all_day' => $entry->isAllDay,
            'actor_id' => (string) $user->id,
            'actor_username' => $user->username,
        ]);
    }
}

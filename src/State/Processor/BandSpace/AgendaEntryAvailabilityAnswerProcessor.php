<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\AgendaEntryAvailabilityAnswer;
use App\ApiResource\BandSpace\AgendaEntryAvailabilityResource;
use App\Entity\User;
use App\Enum\BandSpace\AvailabilityAnswer;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\AgendaEntryAvailabilityRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\AgendaOccurrenceLocator;
use App\Service\BandSpace\BandSpaceChangeSignal;
use App\Service\Builder\BandSpace\AgendaEntryAvailabilityBuilder;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The caller's own answer for one date (#1000). Not recorded in the activity feed: an answer is a
 * state, read from the date itself, and one per member per gig would bury everything else.
 *
 * @implements ProcessorInterface<AgendaEntryAvailabilityAnswer, AgendaEntryAvailabilityResource>
 */
readonly class AgendaEntryAvailabilityAnswerProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private AgendaOccurrenceLocator $occurrenceLocator,
        private AgendaEntryAvailabilityRepository $availabilityRepository,
        private AgendaEntryAvailabilityBuilder $availabilityBuilder,
        private BandSpaceChangeSignal $changeSignal,
        private Security $security,
    ) {
    }

    /**
     * @param AgendaEntryAvailabilityAnswer $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AgendaEntryAvailabilityResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);
        [$entry, $occurrenceDate] = $this->occurrenceLocator->locate($bandSpace, (string) $uriVariables['entryId'], $data->occurrenceDate);

        if (AgendaOccurrenceLocator::isPast($occurrenceDate)) {
            throw new UnprocessableEntityHttpException('Cette date est passée, les disponibilités ne peuvent plus changer');
        }

        $this->availabilityRepository->upsert($entry, new DateTimeImmutable($occurrenceDate), $membership, AvailabilityAnswer::from((string) $data->answer));
        $this->changeSignal->changed($bandSpace, BandSpaceModule::Agenda);

        return $this->availabilityBuilder->build($bandSpace, $entry, $occurrenceDate, $membership);
    }
}

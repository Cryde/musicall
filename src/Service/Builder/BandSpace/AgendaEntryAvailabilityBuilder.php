<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\AgendaEntryAvailabilityResource;
use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\AgendaEntryAvailability;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\AgendaEntryAvailabilityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Service\BandSpace\AgendaAvailabilityTally;
use App\Service\BandSpace\AgendaOccurrenceLocator;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use DateTimeImmutable;
use DateTimeInterface;

readonly class AgendaEntryAvailabilityBuilder
{
    public function __construct(
        private BandSpaceMembershipRepository $membershipRepository,
        private AgendaEntryAvailabilityRepository $availabilityRepository,
        private MemberAbsenceRepository $memberAbsenceRepository,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    public function build(BandSpace $bandSpace, AgendaEntry $entry, string $occurrenceDate, BandSpaceMembership $viewer): AgendaEntryAvailabilityResource
    {
        [$members, $answers, $resolved] = $this->resolve($bandSpace, $entry, $occurrenceDate);

        $answeredAt = [];
        foreach ($answers as $answer) {
            $answeredAt[(string) $answer->membership->id] = $answer->answeredAt->format(DateTimeInterface::ATOM);
        }

        $isUpcoming = !AgendaOccurrenceLocator::isPast($occurrenceDate);
        $isAuthorOrAdmin = (string) $entry->creator->id === (string) $viewer->user->id || $viewer->role === Role::Admin;

        $resource = new AgendaEntryAvailabilityResource();
        $resource->bandSpaceId = (string) $bandSpace->id;
        $resource->entryId = (string) $entry->id;
        $resource->occurrenceDate = $occurrenceDate;
        $resource->canAnswer = $isUpcoming;
        $resource->canRemind = $isUpcoming && $isAuthorOrAdmin;
        $resource->myAnswer = $resolved[(string) $viewer->id] ?? null;
        $resource->totals = AgendaAvailabilityTally::totals($resolved);

        foreach ($members as $member) {
            $memberId = (string) $member->id;
            $resource->members[] = [
                'membership_id' => $memberId,
                'user_id' => (string) $member->user->id,
                'display_name' => $member->displayName(),
                'profile_picture_url' => $this->profilePictureUrlBuilder->build($member->user),
                'answer' => $resolved[$memberId],
                'answered_at' => $answeredAt[$memberId] ?? null,
            ];
        }

        return $resource;
    }

    /**
     * The members « Relancer » reaches: no answer and no declared absence.
     *
     * @return BandSpaceMembership[]
     */
    public function pendingMemberships(BandSpace $bandSpace, AgendaEntry $entry, string $occurrenceDate): array
    {
        [$members, , $resolved] = $this->resolve($bandSpace, $entry, $occurrenceDate);

        return array_values(array_filter($members, static fn (BandSpaceMembership $member): bool => $resolved[(string) $member->id] === null));
    }

    /**
     * @return array{BandSpaceMembership[], AgendaEntryAvailability[], array<string, string|null>}
     */
    private function resolve(BandSpace $bandSpace, AgendaEntry $entry, string $occurrenceDate): array
    {
        // Active members only, in roster order.
        $members = $this->membershipRepository->findByBandSpace($bandSpace);
        $day = new DateTimeImmutable($occurrenceDate);
        $answers = $this->availabilityRepository->findForOccurrence($entry, $day);
        $resolved = AgendaAvailabilityTally::resolve(
            array_values(array_map(static fn (BandSpaceMembership $member): string => (string) $member->id, $members)),
            $answers,
            $this->memberAbsenceRepository->findOverlappingForBand($bandSpace, $day, $day),
            $occurrenceDate,
        );

        return [$members, $answers, $resolved];
    }
}

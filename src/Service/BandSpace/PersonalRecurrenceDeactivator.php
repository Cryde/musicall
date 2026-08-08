<?php

declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Service\BandSpace\File\BandSpaceFileSourceDetacher;
use DateTime;

/**
 * Stops the personal finance recurrences of a member who is on their way out, whether they left, were
 * removed by an admin or deleted their MusicAll account.
 *
 * Past entries are kept because the band's books have to stay balanced, only the entries the recurrence
 * had planned ahead are dropped: nobody owes a monthly contribution for a band they are no longer in.
 *
 * Shared by the three departure paths so a member who disappears one way does not keep generating
 * entries while a member who disappears another way stops.
 */
readonly class PersonalRecurrenceDeactivator
{
    public function __construct(
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceFileSourceDetacher $fileSourceDetacher,
    ) {
    }

    /**
     * @param User $actor whoever triggered the departure: the member themselves, or the admin who
     *                    removed them. The file feed drops activities with no actor, so the detached
     *                    files would go unreported without one.
     */
    public function deactivateForMember(BandSpaceMembership $membership, User $actor): void
    {
        $now = new DateTime();

        foreach ($this->financeRecurrenceRepository->findActivePersonalByMember($membership) as $recurrence) {
            $recurrence->isActive = false;
            $recurrence->updateDatetime = new DateTime();

            $this->fileSourceDetacher->detachDeletedSources(
                $membership->bandSpace,
                'finance',
                $this->financeEntryRepository->findPlannedLabelsByRecurrence($recurrence, $now),
                $actor,
            );
            $this->financeEntryRepository->deletePlannedByRecurrence($recurrence, $now);
        }
    }
}

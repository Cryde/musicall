<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;

/**
 * Kills the public links of files that are being trashed (#823).
 *
 * Archiving used to leave band_space_file_share untouched. The admin share list hides shares of
 * archived files, so the link vanished from the only screen that can revoke it while the row stayed
 * live underneath, and a restore weeks later silently reopened the URL until its original expiry.
 * Revoking on the way into the trash makes the disappearance real: a restore brings the file back,
 * never the link, because nothing clears revocation_datetime once it is set.
 *
 * One implementation rather than one per delete path: the single file endpoint and the folder cascade
 * both archive files, and either of them forgetting produces a link the band cannot see or revoke.
 */
readonly class BandSpaceFileShareRevoker
{
    public function __construct(
        private BandSpaceFileShareRepository $shareRepository,
        private BandSpaceActivityRecorder $activityRecorder,
    ) {
    }

    /**
     * Nothing is flushed here: the revocations join the caller's own unit of work so the files and
     * their links are archived together, or not at all.
     *
     * @param string[] $fileIds
     */
    public function revokeForArchivedFiles(BandSpace $bandSpace, array $fileIds, User $actor): void
    {
        $now = new DateTimeImmutable();

        foreach ($this->shareRepository->findUnrevokedByFileIds($fileIds) as $share) {
            $share->revocationDatetime = $now;

            $this->activityRecorder->record(
                $bandSpace,
                BandSpaceModule::File,
                BandSpaceFileActivityType::ShareRevoked,
                resourceId: (string) $share->bandSpaceFile->id,
                actor: $actor,
                payload: ['share_id' => (string) $share->id],
            );
        }
    }
}

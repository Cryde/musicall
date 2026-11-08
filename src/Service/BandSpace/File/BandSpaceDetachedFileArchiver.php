<?php declare(strict_types=1);

namespace App\Service\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\Role;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The `?archive=true` of the detach endpoints: the trash button reached from a task, a note or a song,
 * so it applies the trash button's rules (#829). Without them any member could trash someone else's
 * file, or pull it from every other source still showing it.
 */
readonly class BandSpaceDetachedFileArchiver
{
    public function __construct(
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceFileArchiver $fileArchiver,
        private BandSpaceFileAttachmentLabels $attachmentLabels,
    ) {
    }

    /**
     * Nothing is flushed here, and the caller has not flushed the detach yet, so a refusal leaves the
     * file attached where it was.
     */
    public function archive(BandSpaceMembership $membership, BandSpaceFileAttachment $detached): void
    {
        $file = $detached->bandSpaceFile;

        if ($file->createdBy->id !== $membership->user->id && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('band_space.file.delete_forbidden');
        }

        $otherSourceTypes = $this->attachmentRepository->findDeleteBlockingSourceTypesByFileIds([(string) $file->id], $detached)[(string) $file->id] ?? [];
        if ($otherSourceTypes !== []) {
            throw new TranslatableHttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'band_space.file.delete_attached',
                ['sources' => $this->attachmentLabels->describe($otherSourceTypes)],
            );
        }

        $this->fileArchiver->archive([$file], $membership->bandSpace, $membership->user);
    }
}

<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class BandSpaceFileRollbackProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileVersionRepository $versionRepository,
        private BandSpaceActivityRecorder $activityRecorder,
    ) {
    }

    public function rollback(BandSpaceFile $file, int $versionNumber, User $user): BandSpaceFile
    {
        $target = $this->versionRepository->findOneByFileAndVersionNumber($file, $versionNumber);
        if (!$target instanceof \App\Entity\BandSpace\BandSpaceFileVersion) {
            throw new TranslatableHttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'band_space.file.version_not_found',
                ['version' => (string) $versionNumber],
            );
        }

        $fromVersionNumber = $file->currentVersion?->versionNumber;
        if ($fromVersionNumber === $versionNumber) {
            throw new UnprocessableEntityHttpException('band_space.file.version_already_current');
        }

        $file->currentVersion = $target;
        $file->updateDatetime = new DateTime();

        $this->activityRecorder->record(
            $file->bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::RolledBack,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'from_version_number' => $fromVersionNumber,
                'to_version_number' => $versionNumber,
            ],
        );

        $this->entityManager->flush();

        return $file;
    }
}

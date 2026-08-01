<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFilePurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Empties one file out of the trash for good, without waiting for app:band-space:purge.
 *
 * Admin only, unlike archiving and restoring: this destroys the stored object, cannot be undone, and
 * frees quota for the whole band.
 *
 * @implements ProcessorInterface<BandSpaceFileResource, void>
 */
readonly class BandSpaceFilePermanentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFilePurger $filePurger,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->adminChecker->checkAdminForWrite((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        // The trash is the only door to this endpoint: a live file has to be deleted normally first, so
        // nobody can skip the grace period by accident.
        if (!$file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new ConflictHttpException('Ce fichier n\'est pas dans la corbeille');
        }

        $originalName = $file->originalName;
        $fileId = (string) $file->id;

        // Recorded before the purge: afterwards there is no entity left to read a name from. The activity
        // keeps its resourceId as a plain string, so it survives the row it points at.
        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Purged,
            resourceId: $fileId,
            actor: $user,
            payload: ['original_name' => $originalName],
        );
        $this->entityManager->flush();

        $this->filePurger->purge($file);
    }
}

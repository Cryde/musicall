<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileVersionRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Takes a file back out of the trash. Mirrors BandSpaceFileDeleteProcessor, which is what put it there.
 *
 * @implements ProcessorInterface<BandSpaceFileResource, BandSpaceFileResource>
 */
readonly class BandSpaceFileRestoreProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileVersionRepository $versionRepository,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceFileBuilder $fileBuilder,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        // findOneByIdAndBandSpace does not filter archived files, which is exactly what the trash needs.
        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        if (!$file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new ConflictHttpException('Ce fichier n\'est pas dans la corbeille');
        }

        // Same rule as archiving: whoever could delete it can bring it back.
        $isOwner = $file->createdBy instanceof User && $file->createdBy->id === $user->id;
        if (!$isOwner && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut restaurer ce fichier');
        }

        // The quota only counts non-archived files, so restoring puts every version of this one back into
        // the band's usage. Refuse rather than let a restore push the space over its limit.
        $this->quotaService->assertCanUpload($bandSpace, $this->versionRepository->sumBytesByFile($file));

        // Only the archive flag comes off. The share links revoked on the way into the trash stay
        // revoked: a link the band watched disappear must not come back to life behind their back.
        $file->archiveDatetime = null;

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Restored,
            resourceId: (string) $file->id,
            actor: $user,
            payload: ['original_name' => $file->originalName],
        );

        $this->entityManager->flush();

        return $this->fileBuilder->buildItem($file);
    }
}

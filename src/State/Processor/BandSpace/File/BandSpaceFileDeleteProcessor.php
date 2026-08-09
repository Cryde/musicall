<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileAttachmentLabels;
use App\Service\BandSpace\File\BandSpaceFileShareRevoker;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileResource, void>
 */
readonly class BandSpaceFileDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceFileShareRevoker $shareRevoker,
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

        [, $membership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $membership->bandSpace);
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile || $file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $isOwner = $file->createdBy instanceof \App\Entity\User && $file->createdBy->id === $user->id;
        if (!$isOwner && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut supprimer ce fichier');
        }

        $attachments = $this->attachmentRepository->findByFile($file);
        if (count($attachments) > 0) {
            $sourceTypes = array_map(static fn ($attachment): string => $attachment->sourceType, $attachments);
            throw new UnprocessableEntityHttpException(sprintf(
                "Ce fichier est attaché à %s. Détachez-le d'abord depuis la ressource concernée.",
                BandSpaceFileAttachmentLabels::describe($sourceTypes),
            ));
        }

        $file->archiveDatetime = new DateTimeImmutable();

        $this->shareRevoker->revokeForArchivedFiles($membership->bandSpace, [(string) $file->id], $user);

        $this->activityRecorder->record(
            $membership->bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Archived,
            resourceId: (string) $file->id,
            actor: $user,
            payload: ['original_name' => $file->originalName],
        );

        $this->entityManager->flush();
    }
}

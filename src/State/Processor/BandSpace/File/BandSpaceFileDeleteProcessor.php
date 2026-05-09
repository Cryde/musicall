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

        [, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $membership->bandSpace);
        if ($file === null || $file->archiveDatetime !== null) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $isOwner = $file->createdBy !== null && $file->createdBy->id === $user->id;
        if (!$isOwner && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut supprimer ce fichier');
        }

        $attachments = $this->attachmentRepository->findByFile($file);
        if (count($attachments) > 0) {
            $sourceTypes = array_unique(array_map(fn ($a): string => $a->sourceType, $attachments));
            throw new UnprocessableEntityHttpException($this->buildAttachmentMessage($sourceTypes));
        }

        $file->archiveDatetime = new DateTimeImmutable();

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

    /**
     * @param string[] $sourceTypes
     */
    private function buildAttachmentMessage(array $sourceTypes): string
    {
        $labels = array_map(static fn (string $type): string => match ($type) {
            'task' => 'une tâche',
            'finance' => 'une entrée financière',
            'note' => 'une note',
            default => 'une autre ressource',
        }, $sourceTypes);

        $list = match (count($labels)) {
            1 => $labels[0],
            2 => $labels[0] . ' et ' . $labels[1],
            default => implode(', ', array_slice($labels, 0, -1)) . ' et ' . end($labels),
        };

        return "Ce fichier est attaché à {$list}. Détachez-le d'abord depuis la ressource concernée.";
    }
}

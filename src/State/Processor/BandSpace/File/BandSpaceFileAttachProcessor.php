<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileAttachInput;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileAttachInput, BandSpaceFileResource>
 */
readonly class BandSpaceFileAttachProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private TaskRepository $taskRepository,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private BandSpaceFileBuilder $fileBuilder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileResource
    {
        /** @var BandSpaceFileAttachInput $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['fileId'], $bandSpace);
        if ($file === null || $file->archiveDatetime !== null) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        if ($data->sourceId === null) {
            throw new UnprocessableEntityHttpException("L'identifiant de la source est requis");
        }

        $sourceLabel = match ($data->sourceType) {
            'task' => $this->resolveTask($data->sourceId, $bandSpace, $user),
            'finance' => $this->resolveFinanceEntry($data->sourceId, $bandSpace, $user),
            default => throw new UnprocessableEntityHttpException('Type de source invalide'),
        };

        $existing = $this->attachmentRepository->findOneByFileAndSource(
            $file,
            (string) $data->sourceType,
            (string) $data->sourceId,
        );
        if ($existing !== null) {
            throw new UnprocessableEntityHttpException('Ce fichier est déjà attaché à cette ressource.');
        }

        $attachment = new BandSpaceFileAttachment();
        $attachment->bandSpaceFile = $file;
        $attachment->sourceType = (string) $data->sourceType;
        $attachment->sourceId = Uuid::fromString((string) $data->sourceId);
        $attachment->attachedBy = $user;
        $this->entityManager->persist($attachment);

        $file->updateDatetime = new \DateTime();

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Attached,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'source_type' => $data->sourceType,
                'source_id' => (string) $data->sourceId,
                'source_label' => $sourceLabel,
            ],
        );

        $this->entityManager->flush();

        return $this->fileBuilder->buildItem($file);
    }

    private function resolveTask(string $taskId, BandSpace $bandSpace, User $user): string
    {
        $task = $this->taskRepository->findOneByIdAndBandSpace($taskId, $bandSpace);
        if ($task === null) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        return $task->title;
    }

    private function resolveFinanceEntry(string $entryId, BandSpace $bandSpace, User $user): string
    {
        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace($entryId, $bandSpace);
        if ($entry === null) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        if ($entry->scope === FinanceEntryScope::Personal && $entry->member?->user->id !== $user->id) {
            throw new AccessDeniedHttpException('Vous ne pouvez modifier que vos propres entrées personnelles');
        }

        return $entry->label;
    }
}

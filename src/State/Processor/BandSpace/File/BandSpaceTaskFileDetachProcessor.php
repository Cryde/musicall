<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileResource, void>
 */
readonly class BandSpaceTaskFileDetachProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private TaskRepository $taskRepository,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $task = $this->taskRepository->findOneByIdAndBandSpace((string) $uriVariables['taskId'], $bandSpace);
        if ($task === null) {
            throw new NotFoundHttpException('Tâche introuvable');
        }

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if ($file === null || $file->archiveDatetime !== null) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $attachment = $this->attachmentRepository->findOneByFileAndSource($file, 'task', (string) $task->id);
        if ($attachment === null) {
            throw new NotFoundHttpException("Le fichier n'est pas attaché à cette tâche");
        }

        $shouldArchive = $this->requestStack->getCurrentRequest()?->query->getBoolean('archive') ?? false;

        $this->entityManager->remove($attachment);
        $file->updateDatetime = new \DateTime();

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Detached,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'source_type' => 'task',
                'source_id' => (string) $task->id,
                'source_label' => $task->title,
            ],
        );

        if ($shouldArchive) {
            $file->archiveDatetime = new DateTimeImmutable();

            $this->activityRecorder->record(
                $bandSpace,
                BandSpaceModule::File,
                BandSpaceFileActivityType::Archived,
                resourceId: (string) $file->id,
                actor: $user,
                payload: ['original_name' => $file->originalName],
            );
        }

        $this->entityManager->flush();
    }
}

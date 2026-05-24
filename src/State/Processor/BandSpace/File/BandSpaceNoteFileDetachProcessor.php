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
use App\Repository\BandSpace\BandSpaceNoteRepository;
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
readonly class BandSpaceNoteFileDetachProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceNoteRepository $noteRepository,
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

        $note = $this->noteRepository->findOneByIdAndBandSpace((string) $uriVariables['noteId'], $bandSpace);
        if (!$note instanceof \App\Entity\BandSpace\BandSpaceNote) {
            throw new NotFoundHttpException('Note introuvable');
        }

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile || $file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $attachment = $this->attachmentRepository->findOneByFileAndSource($file, 'note', (string) $note->id);
        if (!$attachment instanceof \App\Entity\BandSpace\BandSpaceFileAttachment) {
            throw new NotFoundHttpException("Le fichier n'est pas attaché à cette note");
        }

        $shouldArchive = $this->requestStack->getCurrentRequest()?->query->getBoolean('archive') ?? false;

        $this->entityManager->remove($attachment);
        $file->updateDatetime = new \DateTime();

        // File feed only — note file detach is triggered automatically by the
        // editor when the user removes an image from the note body, and the
        // resulting note_content_updated activity already captures the change
        // in the Notes feed. A separate note_file_detached row would just be
        // noise on every edit. (Contrast with Song / Setlist where detach is
        // an explicit user-driven action.)
        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Detached,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'source_type' => 'note',
                'source_id' => (string) $note->id,
                'source_label' => $note->title,
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

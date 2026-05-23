<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\SongRepository;
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
readonly class BandSpaceSongFileDetachProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SongRepository $songRepository,
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

        $song = $this->songRepository->findOneByIdAndBandSpace((string) $uriVariables['songId'], $bandSpace);
        if (!$song instanceof \App\Entity\BandSpace\Song) {
            throw new NotFoundHttpException('Chanson introuvable');
        }

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile || $file->archiveDatetime instanceof DateTimeImmutable) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $attachment = $this->attachmentRepository->findOneByFileAndSource($file, 'song', (string) $song->id);
        if (!$attachment instanceof \App\Entity\BandSpace\BandSpaceFileAttachment) {
            throw new NotFoundHttpException("Le fichier n'est pas attaché à cette chanson");
        }

        $shouldArchive = $this->requestStack->getCurrentRequest()?->query->getBoolean('archive') ?? false;

        $this->entityManager->remove($attachment);
        $file->updateDatetime = new \DateTime();

        // File feed: "this file was detached from song X" - payload describes the SOURCE.
        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Detached,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'source_type' => 'song',
                'source_id' => (string) $song->id,
                'source_label' => $song->title,
            ],
        );

        // Setlist feed: "a file was detached from this song" - payload describes the FILE.
        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::Setlist,
            BandSpaceSetlistActivityType::SongFileDetached,
            resourceId: (string) $song->id,
            actor: $user,
            payload: [
                'file_id' => (string) $file->id,
                'original_name' => $file->originalName,
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

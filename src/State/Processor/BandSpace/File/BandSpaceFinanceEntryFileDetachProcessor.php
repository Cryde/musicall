<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
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
readonly class BandSpaceFinanceEntryFileDetachProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
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

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['entryId'], $bandSpace);
        if (!$entry instanceof \App\Entity\BandSpace\FinanceEntry) {
            throw new NotFoundHttpException('Entrée introuvable');
        }

        if ($entry->scope === FinanceEntryScope::Personal && $entry->member?->user->id !== $user->id) {
            throw new AccessDeniedHttpException('Vous ne pouvez modifier que vos propres entrées personnelles');
        }

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$file instanceof \App\Entity\BandSpace\BandSpaceFile || $file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $attachment = $this->attachmentRepository->findOneByFileAndSource($file, 'finance', (string) $entry->id);
        if (!$attachment instanceof \App\Entity\BandSpace\BandSpaceFileAttachment) {
            throw new NotFoundHttpException("Le fichier n'est pas attaché à cette entrée");
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
                'source_type' => 'finance',
                'source_id' => (string) $entry->id,
                'source_label' => $entry->label,
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

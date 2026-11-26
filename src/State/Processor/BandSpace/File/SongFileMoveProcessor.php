<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\ApiResource\BandSpace\File\SongFileMove;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\SongWriteChecker;
use App\Service\BandSpace\BandSpaceChangeSignal;
use App\Service\BandSpace\File\SongFileFolderResolver;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SongFileMove, BandSpaceFileResource>
 */
readonly class SongFileMoveProcessor implements ProcessorInterface
{
    public function __construct(
        private SongWriteChecker $songWriteChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private SongFileFolderResolver $folderResolver,
        private BandSpaceFileBuilder $fileBuilder,
        private BandSpaceChangeSignal $changeSignal,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileResource
    {
        /** @var SongFileMove $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $song] = $this->songWriteChecker->checkSongForWrite(
            (string) $uriVariables['bandSpaceId'],
            (string) $uriVariables['songId'],
            $user,
        );

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['fileId'], $bandSpace);
        if (!$file instanceof BandSpaceFile || $file->isArchived()) {
            throw new NotFoundHttpException('band_space.file.not_found');
        }

        $attachment = $this->attachmentRepository->findOneByFileAndSource($file, 'song', (string) $song->id);
        if (!$attachment instanceof BandSpaceFileAttachment) {
            throw new NotFoundHttpException('band_space.file.not_attached_to_song');
        }

        $attachment->songFolder = $this->folderResolver->resolve($data->folderId, $song);
        $this->entityManager->flush();

        $this->changeSignal->changed($bandSpace, BandSpaceModule::File);

        return $this->fileBuilder->buildItem($file);
    }
}

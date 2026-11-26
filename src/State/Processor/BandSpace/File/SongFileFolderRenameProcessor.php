<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\SongFileFolderRename;
use App\ApiResource\BandSpace\File\SongFileFolderResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\SongFileFolderRepository;
use App\Security\BandSpace\SongWriteChecker;
use App\Service\BandSpace\BandSpaceChangeSignal;
use App\Service\Builder\BandSpace\File\SongFileFolderBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<SongFileFolderRename, SongFileFolderResource>
 */
readonly class SongFileFolderRenameProcessor implements ProcessorInterface
{
    public function __construct(
        private SongWriteChecker $songWriteChecker,
        private SongFileFolderRepository $folderRepository,
        private SongFileFolderBuilder $folderBuilder,
        private BandSpaceChangeSignal $changeSignal,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SongFileFolderResource
    {
        /** @var SongFileFolderRename $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $song, $folder] = $this->songWriteChecker->checkFolderForWrite(
            (string) $uriVariables['bandSpaceId'],
            (string) $uriVariables['songId'],
            (string) $uriVariables['id'],
            $user,
        );

        $name = $data->name !== null ? trim($data->name) : null;
        if ($name !== null && $name !== $folder->name) {
            if ($this->folderRepository->siblingNameExists($song, $folder->parent, $name, $folder)) {
                throw new UnprocessableEntityHttpException('band_space.folder.already_exists');
            }

            $folder->name = $name;
            $folder->updateDatetime = new DateTime();
            $this->entityManager->flush();

            $this->changeSignal->changed($bandSpace, BandSpaceModule::File);
        }

        return $this->folderBuilder->buildItem($folder);
    }
}

<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\SongFileFolderCreate;
use App\ApiResource\BandSpace\File\SongFileFolderResource;
use App\Entity\BandSpace\SongFileFolder;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\SongFileFolderRepository;
use App\Security\BandSpace\SongWriteChecker;
use App\Service\BandSpace\BandSpaceChangeSignal;
use App\Service\BandSpace\File\SongFileFolderResolver;
use App\Service\Builder\BandSpace\File\SongFileFolderBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * No activity line: sorting a song's files is housekeeping, like reordering a setlist.
 *
 * @implements ProcessorInterface<SongFileFolderCreate, SongFileFolderResource>
 */
readonly class SongFileFolderCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private SongWriteChecker $songWriteChecker,
        private SongFileFolderResolver $folderResolver,
        private SongFileFolderRepository $folderRepository,
        private SongFileFolderBuilder $folderBuilder,
        private BandSpaceChangeSignal $changeSignal,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SongFileFolderResource
    {
        /** @var SongFileFolderCreate $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $song] = $this->songWriteChecker->checkSongForWrite(
            (string) $uriVariables['bandSpaceId'],
            (string) $uriVariables['songId'],
            $user,
        );

        $parent = $this->folderResolver->resolve($data->parentId, $song);
        if ($parent instanceof SongFileFolder && $parent->depth() >= SongFileFolder::MAX_DEPTH) {
            throw new TranslatableHttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'band_space.song_file_folder.max_depth_exceeded',
                ['max' => (string) SongFileFolder::MAX_DEPTH],
            );
        }

        $name = trim($data->name);
        if ($this->folderRepository->siblingNameExists($song, $parent, $name)) {
            throw new UnprocessableEntityHttpException('band_space.folder.already_exists');
        }

        $folder = new SongFileFolder();
        $folder->song = $song;
        $folder->parent = $parent;
        $folder->name = $name;
        $folder->createdBy = $user;

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        $this->changeSignal->changed($bandSpace, BandSpaceModule::File);

        return $this->folderBuilder->buildItem($folder);
    }
}

<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFolderCreate;
use App\ApiResource\BandSpace\File\BandSpaceFolderResource;
use App\Entity\BandSpace\BandSpaceFolder;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFolderActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\File\BandSpaceFolderBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFolderCreate, BandSpaceFolderResource>
 */
readonly class BandSpaceFolderCreateProcessor implements ProcessorInterface
{
    public const int MAX_DEPTH = 6;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFolderBuilder $folderBuilder,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFolderResource
    {
        /** @var BandSpaceFolderCreate $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $parent = null;
        if ($data->parentId !== null && $data->parentId !== '') {
            $parent = $this->folderRepository->findOneByIdAndBandSpace($data->parentId, $bandSpace);
            if (!$parent instanceof \App\Entity\BandSpace\BandSpaceFolder) {
                throw new BadRequestHttpException('Dossier parent introuvable dans ce Band Space');
            }
        }

        $depth = $parent instanceof \App\Entity\BandSpace\BandSpaceFolder ? $this->folderRepository->computeDepth($parent) + 1 : 0;
        if ($depth >= self::MAX_DEPTH) {
            throw new UnprocessableEntityHttpException(sprintf('La profondeur maximale (%d) est dépassée', self::MAX_DEPTH));
        }

        if ($this->folderRepository->siblingNameExists($bandSpace, $parent, $data->name)) {
            throw new UnprocessableEntityHttpException('Un dossier avec ce nom existe déjà à cet emplacement');
        }

        $folder = new BandSpaceFolder();
        $folder->bandSpace = $bandSpace;
        $folder->parent = $parent;
        $folder->name = trim($data->name);
        $folder->createdBy = $user;

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFolderActivityType::FolderCreated,
            resourceId: (string) $folder->id,
            actor: $user,
            payload: [
                'name' => $folder->name,
                'parent_id' => $parent instanceof \App\Entity\BandSpace\BandSpaceFolder ? (string) $parent->id : null,
            ],
        );
        $this->entityManager->flush();

        return $this->folderBuilder->buildItem($folder, $depth);
    }
}

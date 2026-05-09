<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFolderResource;
use App\Entity\BandSpace\BandSpaceFolder;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFolderActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\File\BandSpaceFolderBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFolderResource, BandSpaceFolderResource>
 */
readonly class BandSpaceFolderUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFolderBuilder $folderBuilder,
        private BandSpaceActivityRecorder $activityRecorder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFolderResource
    {
        /** @var BandSpaceFolderResource $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $folder = $this->folderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if ($folder === null) {
            throw new NotFoundHttpException('Dossier introuvable');
        }

        $isOwner = $folder->createdBy !== null && $folder->createdBy->id === $user->id;
        if (!$isOwner && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul le créateur ou un administrateur peut modifier ce dossier');
        }

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $oldName = $folder->name;
        $oldParentId = $folder->parent !== null ? (string) $folder->parent->id : null;
        $renamed = false;
        $moved = false;

        if (array_key_exists('name', $payload)) {
            $newName = trim((string) $payload['name']);
            if ($newName === '') {
                throw new BadRequestHttpException('Le nom ne peut pas être vide');
            }
            if ($newName !== $folder->name
                && $this->folderRepository->siblingNameExists($bandSpace, $folder->parent, $newName, $folder)
            ) {
                throw new UnprocessableEntityHttpException('Un dossier avec ce nom existe déjà à cet emplacement');
            }
            if ($newName !== $folder->name) {
                $renamed = true;
            }
            $folder->name = $newName;
        }

        if (array_key_exists('parent_id', $payload) || array_key_exists('parentId', $payload)) {
            $rawParentId = $payload['parent_id'] ?? $payload['parentId'] ?? null;
            $newParent = null;
            if ($rawParentId !== null && $rawParentId !== '') {
                $newParent = $this->folderRepository->findOneByIdAndBandSpace((string) $rawParentId, $bandSpace);
                if ($newParent === null) {
                    throw new BadRequestHttpException('Dossier parent introuvable dans ce Band Space');
                }
                if ((string) $newParent->id === (string) $folder->id) {
                    throw new UnprocessableEntityHttpException('Un dossier ne peut pas être son propre parent');
                }
                $descendantIds = $this->folderRepository->findDescendantIds($folder);
                if (in_array((string) $newParent->id, $descendantIds, true)) {
                    throw new UnprocessableEntityHttpException('Un dossier ne peut pas être déplacé dans un de ses descendants');
                }
            }

            if ($newParent !== $folder->parent) {
                $newParentDepth = $newParent !== null ? $this->folderRepository->computeDepth($newParent) : -1;
                if ($newParentDepth + 1 >= BandSpaceFolderCreateProcessor::MAX_DEPTH) {
                    throw new UnprocessableEntityHttpException(sprintf('La profondeur maximale (%d) est dépassée', BandSpaceFolderCreateProcessor::MAX_DEPTH));
                }
                if ($this->folderRepository->siblingNameExists($bandSpace, $newParent, $folder->name, $folder)) {
                    throw new UnprocessableEntityHttpException('Un dossier avec ce nom existe déjà à cet emplacement');
                }
                $folder->parent = $newParent;
                $moved = true;
            }
        }

        $folder->updateDatetime = new DateTime();

        if ($renamed) {
            $this->activityRecorder->record(
                $bandSpace,
                BandSpaceModule::File,
                BandSpaceFolderActivityType::FolderRenamed,
                resourceId: (string) $folder->id,
                actor: $user,
                payload: ['from' => $oldName, 'to' => $folder->name],
            );
        }

        if ($moved) {
            $this->activityRecorder->record(
                $bandSpace,
                BandSpaceModule::File,
                BandSpaceFolderActivityType::FolderMoved,
                resourceId: (string) $folder->id,
                actor: $user,
                payload: [
                    'from_parent_id' => $oldParentId,
                    'to_parent_id' => $folder->parent !== null ? (string) $folder->parent->id : null,
                ],
            );
        }

        $this->entityManager->flush();

        return $this->folderBuilder->buildItem($folder, $this->folderRepository->computeDepth($folder));
    }
}

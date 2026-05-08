<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\BandSpaceFolderResource;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFolderBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<BandSpaceFolderResource>
 */
readonly class BandSpaceFolderItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFolderBuilder $folderBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?BandSpaceFolderResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $folder = $this->folderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if ($folder === null) {
            throw new NotFoundHttpException('Dossier introuvable');
        }

        return $this->folderBuilder->buildItem($folder, $this->folderRepository->computeDepth($folder));
    }
}

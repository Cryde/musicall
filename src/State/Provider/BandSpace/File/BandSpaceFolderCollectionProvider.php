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

/**
 * @implements ProviderInterface<BandSpaceFolderResource>
 */
readonly class BandSpaceFolderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFolderBuilder $folderBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return BandSpaceFolderResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $folders = $this->folderRepository->findTree($bandSpace);

        return $this->folderBuilder->buildTree($folders);
    }
}

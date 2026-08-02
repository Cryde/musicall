<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderResource;
use App\Entity\BandSpace\TechRider;
use App\Entity\User;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TechRider\TechRiderBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<TechRiderResource>
 */
readonly class TechRiderItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderBuilder $techRiderBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?TechRiderResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // Archived riders stay readable: they are restored and duplicated from here.
        $techRider = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$techRider instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        return $this->techRiderBuilder->buildItem($techRider);
    }
}

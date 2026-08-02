<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderResource;
use App\Entity\User;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TechRider\TechRiderBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<TechRiderResource>
 */
readonly class TechRiderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderBuilder $techRiderBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return TechRiderResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $archivedOnly = $this->requestStack->getCurrentRequest()?->query->getBoolean('archived') ?? false;

        return $this->techRiderBuilder->buildFromList(
            $this->techRiderRepository->findByBandSpace($bandSpace, $archivedOnly),
        );
    }
}

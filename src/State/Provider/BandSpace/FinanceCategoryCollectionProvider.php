<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceCategoryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class FinanceCategoryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private FinanceCategoryBuilder $financeCategoryBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return FinanceCategoryResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $categories = $this->financeCategoryRepository->findByBandSpace($bandSpace);

        return $this->financeCategoryBuilder->buildFromList($categories);
    }
}

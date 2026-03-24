<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceRecurrenceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class FinanceRecurrenceCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private FinanceRecurrenceBuilder $financeRecurrenceBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return FinanceRecurrenceResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $recurrences = $this->financeRecurrenceRepository->findByBandSpace($bandSpace);

        return $this->financeRecurrenceBuilder->buildFromList($recurrences);
    }
}

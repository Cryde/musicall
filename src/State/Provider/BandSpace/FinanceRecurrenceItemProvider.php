<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Entity\User;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceRecurrenceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<FinanceRecurrenceResource>
 */
readonly class FinanceRecurrenceItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceRecurrenceRepository $financeRecurrenceRepository,
        private FinanceRecurrenceBuilder $financeRecurrenceBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FinanceRecurrenceResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $recurrence = $this->financeRecurrenceRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$recurrence) {
            throw new NotFoundHttpException('Récurrence introuvable');
        }

        return $this->financeRecurrenceBuilder->buildItem($recurrence);
    }
}

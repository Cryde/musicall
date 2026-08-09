<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\BandSpace\FinanceRecurrence;
use App\Entity\User;
use App\Repository\BandSpace\FinanceRecurrenceRepository;
use App\ApiResource\BandSpace\Finance\FinanceRecurrenceResource;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\FinanceRecurrenceOwnerChecker;
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
        private FinanceRecurrenceOwnerChecker $ownerChecker,
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

        [$bandSpace, $viewer] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // A personal recurrence carries its own label and amount, so it is as private as the entries it
        // plans. Filtered here rather than in the query because ownership is not a column on the
        // recurrence: it is read from the entries, which is the rule the write checks already use.
        $recurrences = array_values(array_filter(
            $this->financeRecurrenceRepository->findByBandSpace($bandSpace),
            fn (FinanceRecurrence $recurrence): bool => $this->ownerChecker->isVisibleTo($recurrence, $viewer),
        ));

        return $this->financeRecurrenceBuilder->buildFromList($recurrences);
    }
}

<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\AgendaEntryAvailabilityResource;
use App\Entity\User;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\AgendaOccurrenceLocator;
use App\Service\Builder\BandSpace\AgendaEntryAvailabilityBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<AgendaEntryAvailabilityResource>
 */
readonly class AgendaEntryAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private AgendaOccurrenceLocator $occurrenceLocator,
        private AgendaEntryAvailabilityBuilder $availabilityBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AgendaEntryAvailabilityResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $viewer] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        // Assert\Date on the operation vouches for the format; `?:` is "was it sent at all".
        $occurrence = ($context['filters']['occurrence'] ?? null) ?: null;
        [$entry, $occurrenceDate] = $this->occurrenceLocator->locate($bandSpace, (string) $uriVariables['entryId'], $occurrence);

        return $this->availabilityBuilder->build($bandSpace, $entry, $occurrenceDate, $viewer);
    }
}

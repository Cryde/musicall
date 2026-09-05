<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\AgendaItem;
use App\Entity\User;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\AgendaAggregator;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class AgendaCollectionProvider implements ProviderInterface
{
    private const DEFAULT_WINDOW_DAYS = 30;

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private AgendaAggregator $agendaAggregator,
        private Security $security,
    ) {
    }

    /**
     * @return AgendaItem[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $viewer] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $filters = $context['filters'] ?? [];
        // The operation's Assert\Date has already refused anything that is not a bare `Y-m-d`, so a
        // value that reaches here parses. `?:` is the "was it sent at all" check that is left: an
        // empty `?from=` passes validation, which skips a blank, and must fall back to the default
        // window rather than to the current second.
        $fromFilter = ($filters['from'] ?? null) ?: null;
        $toFilter = ($filters['to'] ?? null) ?: null;

        $from = $fromFilter !== null ? new DateTimeImmutable($fromFilter) : new DateTimeImmutable('today');
        // Entries are instants, so an inclusive `to` has to run to the end of its day: a bound that
        // stopped at midnight would drop an evening rehearsal on the last day of the window.
        $to = $toFilter !== null
            ? (new DateTimeImmutable($toFilter))->setTime(23, 59, 59)
            : $from->modify('+' . self::DEFAULT_WINDOW_DAYS . ' days')->setTime(23, 59, 59);

        return $this->agendaAggregator->aggregate($bandSpace, $viewer, $from, $to);
    }
}

<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\MemberAbsenceResource;
use App\Entity\User;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\MemberAbsenceBuilder;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class MemberAbsenceCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_WINDOW_DAYS = 30;

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MemberAbsenceRepository $memberAbsenceRepository,
        private MemberAbsenceBuilder $memberAbsenceBuilder,
        private Security $security,
    ) {
    }

    /**
     * Every member's absences, not just the reader's: shared visibility is the whole point, and a
     * band that hides availability from itself gains nothing. There is deliberately no member filter
     * either - a band is a handful of people, so both consumers narrow client side off one response.
     *
     * @return MemberAbsenceResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $viewer] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $filters = $context['filters'] ?? [];
        // Assert\Date on the operation vouches for the format and refuses a `?from[]=x` array, so a
        // value that reaches here parses to midnight on its own day. `?:` is the "was it sent at
        // all" check that is left, since validation skips a blank.
        $fromFilter = ($filters['from'] ?? null) ?: null;
        $toFilter = ($filters['to'] ?? null) ?: null;

        $from = $fromFilter !== null ? new DateTimeImmutable($fromFilter) : new DateTimeImmutable('today');
        $to = $toFilter !== null
            ? new DateTimeImmutable($toFilter)
            : $from->modify('+' . self::DEFAULT_WINDOW_DAYS . ' days');

        return $this->memberAbsenceBuilder->buildFromList(
            $this->memberAbsenceRepository->findOverlappingForBand($bandSpace, $from, $to),
            $viewer,
        );
    }
}

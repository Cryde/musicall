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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        $from = $this->parseDate($filters['from'] ?? null) ?? new DateTimeImmutable('today');
        $to = $this->parseDate($filters['to'] ?? null) ?? $from->modify('+' . self::DEFAULT_WINDOW_DAYS . ' days');

        return $this->memberAbsenceBuilder->buildFromList(
            $this->memberAbsenceRepository->findOverlappingForBand($bandSpace, $from, $to),
            $viewer,
        );
    }

    /**
     * Anything but a usable date string reads as absent, so the caller falls back to its default
     * window. `?from[]=x` arrives as an array and would otherwise be a TypeError, so a 500 for what
     * is really a malformed request.
     */
    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            // The column is a DATE, so the window is compared day to day: any time on the value is
            // dropped rather than letting an afternoon `to` exclude that same day's absences.
            return (new DateTimeImmutable($value))->setTime(0, 0);
        } catch (\Exception) {
            throw new BadRequestHttpException('Date invalide');
        }
    }
}

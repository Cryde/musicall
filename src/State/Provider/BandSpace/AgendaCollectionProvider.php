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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $filters = $context['filters'] ?? [];
        $from = $this->parseDatetime($filters['from'] ?? null) ?? (new DateTimeImmutable('today'));
        $to = $this->parseDatetime($filters['to'] ?? null) ?? $from->modify('+' . self::DEFAULT_WINDOW_DAYS . ' days')->setTime(23, 59, 59);

        return $this->agendaAggregator->aggregate($bandSpace, $from, $to);
    }

    private function parseDatetime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new BadRequestHttpException('Date invalide');
        }
    }
}

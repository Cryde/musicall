<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\BandSpaceActivityResource;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityFilter;
use App\Service\Builder\BandSpace\BandSpaceActivityBuilder;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<BandSpaceActivityResource>
 */
readonly class BandSpaceActivityCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceActivityRepository $bandSpaceActivityRepository,
        private BandSpaceActivityBuilder $bandSpaceActivityBuilder,
        private Security $security,
        private RequestStack $requestStack,
        private Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $page = $this->pagination->getPage($context);
        $itemsPerPage = $this->pagination->getLimit($operation, $context);
        $offset = $this->pagination->getOffset($operation, $context);

        $filter = $this->buildFilter($itemsPerPage, $offset);

        $entities = $this->bandSpaceActivityRepository->findForBandSpace($bandSpace, $filter);
        $totalItems = $this->bandSpaceActivityRepository->countForBandSpace($bandSpace, $filter);

        $dtos = $this->bandSpaceActivityBuilder->buildFromList($entities);

        return new TraversablePaginator(
            new \ArrayIterator($dtos),
            $page,
            $itemsPerPage,
            $totalItems,
        );
    }

    private function buildFilter(int $limit, int $offset): BandSpaceActivityFilter
    {
        $query = $this->requestStack->getCurrentRequest()?->query;

        $modules = [];
        $rawModules = $query?->all('module') ?? [];
        foreach ($rawModules as $rawModule) {
            $module = BandSpaceModule::tryFrom((string) $rawModule);
            if ($module !== null) {
                $modules[] = $module;
            }
        }

        $actorId = $query?->getString('actor_id') ?: null;
        $type = $query?->getString('type') ?: null;
        $from = $this->parseDate($query?->getString('from') ?: null, 'from');
        $to = $this->parseDate($query?->getString('to') ?: null, 'to');

        return new BandSpaceActivityFilter(
            modules: $modules,
            actorId: $actorId,
            type: $type,
            from: $from,
            to: $to,
            limit: $limit,
            offset: $offset,
        );
    }

    private function parseDate(?string $value, string $field): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            throw new BadRequestHttpException(sprintf('Paramètre "%s" : date invalide', $field));
        }
    }
}
